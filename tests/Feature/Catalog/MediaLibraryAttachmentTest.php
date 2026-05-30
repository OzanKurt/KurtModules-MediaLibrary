<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

it('persists an attachment with role + position', function () {
    $item = MediaLibraryItem::factory()->create();

    $attachment = MediaLibraryAttachment::factory()->create([
        'item_id' => $item->id,
        'attachable_type' => 'demo_post',
        'attachable_id' => 42,
        'role' => 'cover',
        'position' => 1,
    ]);

    expect($attachment->role)->toBe('cover');
    expect($attachment->position)->toBe(1);
    expect($attachment->item?->id)->toBe($item->id);
});

it('belongs to the item it points at', function () {
    $attachment = MediaLibraryAttachment::factory()->create();

    expect($attachment->item)->not->toBeNull();
    expect($attachment->item?->id)->toBe($attachment->item_id);
});
