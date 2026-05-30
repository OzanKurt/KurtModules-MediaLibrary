<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

/**
 * @mixin Model
 */
trait IsMediaLibraryOwner
{
    /**
     * @return MorphMany<MediaLibraryItem, $this>
     */
    public function mediaLibraryItems(): MorphMany
    {
        return $this->morphMany(MediaLibraryItem::class, 'owner');
    }

    /**
     * @return MorphMany<MediaLibraryFolder, $this>
     */
    public function mediaLibraryFolders(): MorphMany
    {
        return $this->morphMany(MediaLibraryFolder::class, 'owner');
    }

    public function getMediaLibraryDisplayName(): string
    {
        return (string) ($this->getAttribute('name') ?? $this->getAttribute('email') ?? $this->getKey());
    }
}
