<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

/**
 * @mixin Model
 */
trait HasMediaLibraryItems
{
    /**
     * @return MorphMany<MediaLibraryAttachment, $this>
     */
    public function mediaItemAttachments(): MorphMany
    {
        return $this->morphMany(MediaLibraryAttachment::class, 'attachable');
    }

    /**
     * @return MorphToMany<MediaLibraryItem, $this>
     */
    public function mediaItems(?string $role = null): MorphToMany
    {
        $relation = $this->morphToMany(
            MediaLibraryItem::class,
            'attachable',
            'media_library_attachments',
            null,
            'item_id'
        )
            ->withPivot(['role', 'position'])
            ->withTimestamps()
            ->orderBy('media_library_attachments.position');

        if ($role !== null) {
            $relation->wherePivot('role', $role);
        }

        return $relation;
    }

    public function attachMediaItem(MediaLibraryItem $item, string $role = 'attachment', ?int $position = null): MediaLibraryAttachment
    {
        if ($position === null) {
            $max = (int) $this->mediaItemAttachments()->where('role', $role)->max('position');
            $position = $max + 1;
        }

        return MediaLibraryAttachment::create([
            'item_id' => $item->getKey(),
            'attachable_type' => $this->getMorphClass(),
            'attachable_id' => $this->getKey(),
            'role' => $role,
            'position' => $position,
        ]);
    }

    public function detachMediaItem(MediaLibraryItem $item, ?string $role = null): void
    {
        $query = $this->mediaItemAttachments()->where('item_id', $item->getKey());

        if ($role !== null) {
            $query->where('role', $role);
        }

        $query->delete();
    }

    public function coverItem(): ?MediaLibraryItem
    {
        return $this->mediaItems('cover')->first();
    }

    public function socialItem(): ?MediaLibraryItem
    {
        return $this->mediaItems('social')->first();
    }
}
