<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemUploaded;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryStorage;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\InvalidUpload;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;

final class UploadCoordinator
{
    public function __construct(
        private readonly MediaSubjectResolver $subjects,
        private readonly MetadataExtractor $extractor,
    ) {}

    /**
     * Server-proxy upload: store the file via spatie and persist
     * the MediaLibraryItem in a single transaction. Synchronous
     * metadata extraction runs as part of the request.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upload(
        UploadedFile $file,
        ?MediaLibraryOwner $owner = null,
        ?MediaLibraryFolder $folder = null,
        array $attributes = [],
    ): MediaLibraryItem {
        /** @var Authenticatable|null $authUser */
        $authUser = auth()->user();
        $owner ??= $this->subjects->defaultOwner($authUser);

        return DB::transaction(function () use ($file, $owner, $folder, $attributes): MediaLibraryItem {
            $storage = MediaLibraryStorage::create([
                'item_uid' => (string) Str::uuid(),
            ]);

            $disk = (string) config('media-library.uploads.disk', 'public');
            $originalName = (string) $file->getClientOriginalName();

            $media = $storage
                ->addMedia($file->getPathname())
                ->preservingOriginal()
                ->usingFileName($originalName)
                ->toMediaCollection('mli', $disk);

            $extracted = $this->extractor->extractSync(
                $media->getPath(),
                (string) $media->mime_type,
            );

            $slugSource = (string) ($attributes['slug'] ?? $originalName);
            $slug = Str::slug($slugSource).'-'.substr($storage->item_uid, 0, 8);

            /** @var array<string, string>|string|null $title */
            $title = $attributes['title'] ?? ['en' => $originalName];

            $authUserId = auth()->id();
            $authUserId = is_int($authUserId) ? $authUserId : null;

            $item = MediaLibraryItem::create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'folder_id' => $folder?->id,
                'storage_id' => $storage->id,
                'slug' => $slug,
                'title' => $title,
                'alt_text' => $attributes['alt_text'] ?? null,
                'caption' => $attributes['caption'] ?? null,
                'description' => $attributes['description'] ?? null,
                'filename' => $originalName,
                'mime_type' => (string) $media->mime_type,
                'byte_size' => (int) $media->size,
                'width' => $extracted['width'] ?? null,
                'height' => $extracted['height'] ?? null,
                'dominant_color' => $extracted['dominant_color'] ?? null,
                'palette' => $extracted['palette'] ?? null,
                'blurhash' => $extracted['blurhash'] ?? null,
                'created_by' => $authUserId,
                'updated_by' => $authUserId,
            ]);

            if ($folder !== null) {
                $folder->increment('item_count');
            }

            ItemUploaded::dispatch($item);

            return $item;
        });
    }

    /**
     * Initiate a presigned direct-to-S3 upload. Validates the supplied
     * filename + mime + size against config and returns a pending upload
     * row carrying the presigned PUT URL + headers + S3 key.
     *
     * @param  array<string, mixed>  $filenameMeta
     */
    public function initiateUpload(?MediaLibraryOwner $owner, array $filenameMeta): MediaLibraryPendingUpload
    {
        /** @var Authenticatable|null $authUser */
        $authUser = auth()->user();
        $owner ??= $this->subjects->defaultOwner($authUser);

        $filename = (string) ($filenameMeta['filename'] ?? '');
        $mimeType = (string) ($filenameMeta['mime_type'] ?? '');
        $byteSize = isset($filenameMeta['byte_size']) ? (int) $filenameMeta['byte_size'] : null;

        if ($filename === '' || $mimeType === '') {
            throw new InvalidUpload('filename and mime_type are required');
        }

        $this->assertMimeAllowed($mimeType);
        $this->assertSizeAllowed($byteSize);

        $disk = (string) config('media-library.uploads.disk', 'public');
        $uploadId = (string) Str::uuid();
        $key = 'media-library/incoming/'.$uploadId.'/'.$filename;

        $ttl = (int) config('media-library.uploads.presigned_ttl_seconds', 900);
        $expiresAt = now()->addSeconds($ttl);

        $presigned = Storage::disk($disk)->temporaryUploadUrl(
            $key,
            $expiresAt,
            ['ContentType' => $mimeType],
        );

        $payload = [
            'url' => (string) ($presigned['url'] ?? ''),
            'headers' => (array) ($presigned['headers'] ?? []),
            'key' => $key,
            'disk' => $disk,
        ];

        $authUserId = auth()->id();
        $authUserId = is_int($authUserId) ? $authUserId : null;

        return MediaLibraryPendingUpload::create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'upload_id' => $uploadId,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'byte_size' => $byteSize,
            'driver' => 's3',
            'driver_payload' => $payload,
            'status' => PendingUploadStatus::Pending,
            'expires_at' => $expiresAt,
            'created_by' => $authUserId,
        ]);
    }

    /**
     * Finalize a presigned upload: locate the pending row, verify the
     * object lives on the target disk, attach it as the spatie Media
     * for a new storage host, run metadata extraction, and persist the
     * MediaLibraryItem. Marks the pending row Completed.
     */
    public function completeUpload(string $uploadId): MediaLibraryItem
    {
        $pending = MediaLibraryPendingUpload::query()
            ->where('upload_id', $uploadId)
            ->first();

        if ($pending === null) {
            throw new InvalidUpload('pending upload not found');
        }

        if ($pending->status !== PendingUploadStatus::Pending) {
            throw new InvalidUpload('pending upload is not in pending state');
        }

        if ($pending->expires_at->isPast()) {
            throw new InvalidUpload('pending upload has expired');
        }

        $payload = $pending->driver_payload;
        $key = (string) ($payload['key'] ?? '');
        $disk = (string) ($payload['disk'] ?? 's3');

        if ($key === '' || ! Storage::disk($disk)->exists($key)) {
            throw new InvalidUpload('uploaded object not found on disk');
        }

        return DB::transaction(function () use ($pending, $key, $disk): MediaLibraryItem {
            $storage = MediaLibraryStorage::create([
                'item_uid' => (string) Str::uuid(),
            ]);

            $media = $storage
                ->addMediaFromDisk($key, $disk)
                ->usingFileName($pending->filename)
                ->toMediaCollection('mli', $disk);

            $extracted = $this->extractor->extractSync(
                $media->getPath(),
                (string) $media->mime_type,
            );

            $slug = Str::slug($pending->filename).'-'.substr($storage->item_uid, 0, 8);

            $authUserId = auth()->id();
            $authUserId = is_int($authUserId) ? $authUserId : null;

            $item = MediaLibraryItem::create([
                'owner_type' => $pending->owner_type,
                'owner_id' => $pending->owner_id,
                'storage_id' => $storage->id,
                'slug' => $slug,
                'title' => ['en' => $pending->filename],
                'filename' => $pending->filename,
                'mime_type' => (string) $media->mime_type,
                'byte_size' => (int) $media->size,
                'width' => $extracted['width'] ?? null,
                'height' => $extracted['height'] ?? null,
                'dominant_color' => $extracted['dominant_color'] ?? null,
                'palette' => $extracted['palette'] ?? null,
                'blurhash' => $extracted['blurhash'] ?? null,
                'created_by' => $authUserId ?? $pending->created_by,
                'updated_by' => $authUserId ?? $pending->created_by,
            ]);

            $pending->forceFill([
                'status' => PendingUploadStatus::Completed,
                'completed_at' => now(),
            ])->save();

            ItemUploaded::dispatch($item);

            return $item;
        });
    }

    /**
     * Mark a pending upload as cancelled. No-op if already in a
     * terminal state, but never throws when the row is missing.
     */
    public function cancelUpload(string $uploadId): void
    {
        $pending = MediaLibraryPendingUpload::query()
            ->where('upload_id', $uploadId)
            ->first();

        if ($pending === null) {
            return;
        }

        if ($pending->status !== PendingUploadStatus::Pending) {
            return;
        }

        $pending->forceFill(['status' => PendingUploadStatus::Cancelled])->save();
    }

    private function assertMimeAllowed(string $mimeType): void
    {
        /** @var array<int, mixed> $allowed */
        $allowed = (array) config('media-library.uploads.allowed_mimes', []);

        if ($allowed === []) {
            return;
        }

        foreach ($allowed as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            if (str_contains($pattern, '*')) {
                $regex = '#^'.str_replace(['/', '*'], ['\/', '.*'], $pattern).'$#i';
                if (preg_match($regex, $mimeType) === 1) {
                    return;
                }

                continue;
            }

            if (strcasecmp($pattern, $mimeType) === 0) {
                return;
            }
        }

        throw new InvalidUpload(sprintf('mime type "%s" is not allowed', $mimeType));
    }

    private function assertSizeAllowed(?int $byteSize): void
    {
        if ($byteSize === null) {
            return;
        }

        $maxKb = (int) config('media-library.uploads.max_size_kb', 0);

        if ($maxKb <= 0) {
            return;
        }

        $maxBytes = $maxKb * 1024;

        if ($byteSize > $maxBytes) {
            throw new InvalidUpload(sprintf('upload size %d bytes exceeds limit of %d kb', $byteSize, $maxKb));
        }
    }
}
