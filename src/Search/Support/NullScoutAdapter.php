<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Search\Support;

use Illuminate\Database\Eloquent\Collection;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Search\Contracts\ScoutAdapter;

/**
 * Safe no-op search adapter default. The package ships no search engine; bind
 * your own implementation to `media-library.contracts.scout` (e.g. wrapping
 * laravel/scout) to index media. All methods are inert here.
 */
final class NullScoutAdapter implements ScoutAdapter
{
    public function index(MediaLibraryItem $item): void {}

    public function unindex(MediaLibraryItem $item): void {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MediaLibraryItem>
     */
    public function search(string $query, array $filters = [], int $limit = 50): Collection
    {
        /** @var Collection<int, MediaLibraryItem> $empty */
        $empty = new Collection;

        return $empty;
    }
}
