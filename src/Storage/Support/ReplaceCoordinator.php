<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemReplaced;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Exceptions\ReplaceFailed;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;
use Kurt\Modules\MediaLibrary\Storage\Support\Concerns\HardensUploads;

final class ReplaceCoordinator
{
    use HardensUploads;

    public function __construct(private readonly MetadataExtractor $extractor) {}

    /**
     * Replace the current spatie Media file for the given item with a
     * new upload (server-proxy via UploadedFile or completed presigned
     * via MediaLibraryPendingUpload). Captures the previous spatie
     * media into a version row, recomputes metadata, invalidates
     * variants, and dispatches ItemReplaced. The MediaLibraryItem id
     * stays stable so attachments continue to resolve.
     */
    public function replace(
        MediaLibraryItem $item,
        UploadedFile|MediaLibraryPendingUpload $new,
        string $changelog,
    ): MediaLibraryItem {
        // Validate the REAL (content-derived) mime + size of a proxied upload
        // BEFORE touching the disk or the transaction, so a replace can never be
        // used to smuggle a disallowed or oversized file past the same guards the
        // initial upload enforces. Presigned (MediaLibraryPendingUpload) sources
        // were already validated at initiate + finalize time by UploadCoordinator.
        if ($new instanceof UploadedFile) {
            $realMime = (string) ($new->getMimeType() ?? $new->getClientMimeType());
            $this->assertMimeAllowed($realMime);

            $realSize = $new->getSize();
            $this->assertSizeAllowed($realSize === false ? null : (int) $realSize);
        }

        return DB::transaction(function () use ($item, $new, $changelog): MediaLibraryItem {
            $storage = $item->storage;

            if ($storage === null) {
                throw new ReplaceFailed('item has no storage host');
            }

            $previousMedia = $storage->getFirstMedia('mli');

            if ($previousMedia === null) {
                throw new ReplaceFailed('storage host has no media to replace');
            }

            $authUserId = auth()->id();
            $authUserId = is_int($authUserId) ? $authUserId : null;

            MediaLibraryVersion::create([
                'item_id' => $item->id,
                'spatie_media_id' => (int) $previousMedia->id,
                'filename' => (string) $previousMedia->file_name,
                'mime_type' => (string) $previousMedia->mime_type,
                'byte_size' => (int) $previousMedia->size,
                'changelog' => $changelog,
                'created_by' => $authUserId,
            ]);

            $previousSpatieMediaId = (int) $previousMedia->id;

            if ($new instanceof UploadedFile) {
                // Default to a PRIVATE disk (matches UploadCoordinator); the
                // share-link controller is the access-controlled read path.
                $disk = (string) config('media-library.uploads.disk', 'local');
                $newMedia = $storage
                    ->addMedia($new->getPathname())
                    ->preservingOriginal()
                    ->usingFileName($this->sanitizeFilename((string) $new->getClientOriginalName()))
                    ->toMediaCollection('mli', $disk);
            } else {
                $payload = $new->driver_payload;
                $key = (string) ($payload['key'] ?? '');
                $disk = (string) ($payload['disk'] ?? 's3');

                if ($key === '') {
                    throw new ReplaceFailed('pending upload payload missing key');
                }

                $newMedia = $storage
                    ->addMediaFromDisk($key, $disk)
                    ->usingFileName($new->filename)
                    ->toMediaCollection('mli', $disk);
            }

            if ((bool) config('media-library.versions.hard_delete_old_files', false)) {
                $previousMedia->delete();
            }

            $extracted = $this->extractor->extractSync(
                $newMedia->getPath(),
                (string) $newMedia->mime_type,
            );

            $item->forceFill([
                'filename' => (string) $newMedia->file_name,
                'mime_type' => (string) $newMedia->mime_type,
                'byte_size' => (int) $newMedia->size,
                'width' => $extracted['width'] ?? null,
                'height' => $extracted['height'] ?? null,
                'dominant_color' => $extracted['dominant_color'] ?? null,
                'palette' => $extracted['palette'] ?? null,
                'blurhash' => $extracted['blurhash'] ?? null,
                'updated_by' => $authUserId,
            ])->save();

            // Invalidate cached ad-hoc variants for this item; they
            // were generated against the previous source file.
            $item->variants()->delete();

            ItemReplaced::dispatch($item, $previousSpatieMediaId);

            return $item->fresh() ?? $item;
        });
    }
}
