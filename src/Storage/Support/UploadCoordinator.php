<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemUploaded;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryStorage;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;

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
}
