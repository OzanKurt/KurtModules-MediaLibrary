<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

it('persists a share link with cast abilities + token', function () {
    $link = ShareLink::factory()->create([
        'token' => 'abc123',
        'abilities' => ['view', 'download'],
    ]);

    expect($link->token)->toBe('abc123');
    expect($link->abilities)->toBe(['view', 'download']);
    expect($link->isActive())->toBeTrue();
});

it('isActive is true for unrevoked + future expiry', function () {
    $link = ShareLink::factory()->create(['expires_at' => now()->addDay()]);

    expect($link->isExpired())->toBeFalse();
    expect($link->isActive())->toBeTrue();
});

it('isActive is true with no expiry and not revoked', function () {
    $link = ShareLink::factory()->noExpiry()->create();

    expect($link->isExpired())->toBeFalse();
    expect($link->isActive())->toBeTrue();
});

it('isActive is false when revoked', function () {
    $link = ShareLink::factory()->revoked()->create();

    expect($link->isExpired())->toBeFalse();
    expect($link->isActive())->toBeFalse();
});

it('isActive is false when expired', function () {
    $link = ShareLink::factory()->expired()->create();

    expect($link->isExpired())->toBeTrue();
    expect($link->isActive())->toBeFalse();
});

it('isActive is false when both revoked + expired', function () {
    $link = ShareLink::factory()->revoked()->expired()->create();

    expect($link->isExpired())->toBeTrue();
    expect($link->isActive())->toBeFalse();
});

it('belongs to its item or folder', function () {
    $item = MediaLibraryItem::factory()->create();
    $folder = MediaLibraryFolder::factory()->create();

    $itemLink = ShareLink::factory()->create(['item_id' => $item->id]);
    $folderLink = ShareLink::factory()->forFolder($folder->id)->create();

    expect($itemLink->item?->id)->toBe($item->id);
    expect($itemLink->folder)->toBeNull();
    expect($folderLink->folder?->id)->toBe($folder->id);
    expect($folderLink->item)->toBeNull();
});
