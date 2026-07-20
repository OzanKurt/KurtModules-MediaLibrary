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
use Kurt\Modules\MediaLibrary\Jobs\ExtractMediaMetadata;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Storage\Support\Concerns\HardensUploads;

final class UploadCoordinator
{
    use HardensUploads;

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

        // Validate the REAL (content-derived) mime + size of the file being
        // stored. Done before any disk write so rejected uploads never leave
        // orphaned bytes on the disk. getMimeType() inspects the file contents
        // (unlike the client-declared getClientMimeType()).
        $realMime = (string) ($file->getMimeType() ?? $file->getClientMimeType());
        $this->assertMimeAllowed($realMime);

        $realSize = $file->getSize();
        $this->assertSizeAllowed($realSize === false ? null : (int) $realSize);

        $item = DB::transaction(function () use ($file, $owner, $folder, $attributes): MediaLibraryItem {
            $storage = MediaLibraryStorage::create([
                'item_uid' => (string) Str::uuid(),
            ]);

            $disk = (string) config('media-library.uploads.disk', 'local');
            $originalName = $this->sanitizeFilename((string) $file->getClientOriginalName());

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

        // Dispatch the async extractor pipeline (exif/GPS, ocr, ai tags, scout)
        // after the item is committed so a queued worker sees the persisted row.
        ExtractMediaMetadata::dispatchFor($item);

        return $item;
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

        // Strip any path components and slug the name before it becomes part of
        // the S3 object key (defends against ../ traversal in the key).
        $filename = $this->sanitizeFilename($filename);

        $this->assertMimeAllowed($mimeType);
        $this->assertSizeAllowed($byteSize);

        $disk = (string) config('media-library.uploads.disk', 'local');
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

        $item = DB::transaction(function () use ($pending, $key, $disk): MediaLibraryItem {
            $storage = MediaLibraryStorage::create([
                'item_uid' => (string) Str::uuid(),
            ]);

            $media = $storage
                ->addMediaFromDisk($key, $disk)
                ->usingFileName($this->sanitizeFilename($pending->filename))
                ->toMediaCollection('mli', $disk);

            // Re-validate against the REAL object now on disk. The mime + size
            // checked at initiate were only the client-declared values, so a
            // client could have PUT an oversized or disallowed object to the
            // presigned URL. Rejecting here rolls back the transaction.
            $this->assertMimeAllowed((string) $media->mime_type);
            $this->assertSizeAllowed((int) $media->size);

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

        ExtractMediaMetadata::dispatchFor($item);

        return $item;
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
}
