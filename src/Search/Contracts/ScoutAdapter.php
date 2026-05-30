<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Search\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

interface ScoutAdapter
{
    public function index(MediaLibraryItem $item): void;

    public function unindex(MediaLibraryItem $item): void;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MediaLibraryItem>
     */
    public function search(string $query, array $filters = [], int $limit = 50): Collection;
}
