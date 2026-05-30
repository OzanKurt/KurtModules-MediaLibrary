<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;

it('persists a saved search with JSON filters', function () {
    $search = MediaLibrarySavedSearch::factory()->create([
        'user_id' => 1,
        'name' => 'Hero images',
        'filters' => ['mime_type' => 'image/*', 'tag' => 'hero'],
    ]);

    expect($search->name)->toBe('Hero images');
    expect($search->filters)->toBe(['mime_type' => 'image/*', 'tag' => 'hero']);
});
