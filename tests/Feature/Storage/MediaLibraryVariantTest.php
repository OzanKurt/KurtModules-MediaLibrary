<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;

it('persists a variant row with spec json + casts', function () {
    $item = MediaLibraryItem::factory()->create();
    $variant = MediaLibraryVariant::factory()->create([
        'item_id' => $item->id,
        'key' => '300x200-crop-jpg-q85',
        'spec' => ['width' => 300, 'height' => 200, 'fit' => 'crop', 'format' => 'jpg', 'quality' => 85],
    ]);

    expect($variant->key)->toBe('300x200-crop-jpg-q85');
    expect($variant->spec['width'])->toBe(300);
    expect($variant->generated_at)->not->toBeNull();
});

it('exposes variants via the item relation', function () {
    $item = MediaLibraryItem::factory()->create();
    MediaLibraryVariant::factory()->count(2)->create(['item_id' => $item->id]);

    expect($item->variants()->count())->toBe(2);
});

it('enforces unique item + key', function () {
    $item = MediaLibraryItem::factory()->create();
    MediaLibraryVariant::factory()->create(['item_id' => $item->id, 'key' => 'fixed-key']);

    expect(fn () => MediaLibraryVariant::factory()->create(['item_id' => $item->id, 'key' => 'fixed-key']))
        ->toThrow(QueryException::class);
});
