<?php

declare(strict_types=1);

use Kurt\Modules\Core\Support\FilamentVersion;
use Kurt\Modules\MediaLibrary\Filament\V5\Resources\ShareLinkResource;
use Kurt\Modules\MediaLibrary\Filament\V5\Resources\ShareLinkResource\Pages\ListShareLinks;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

beforeEach(function () {
    if (FilamentVersion::major() !== 5) {
        $this->markTestSkipped('Filament v5 is not installed.');
    }
});

it('targets the ShareLink model and registers a list + view page (read-mostly, no create/edit)', function () {
    expect(ShareLinkResource::getModel())->toBe(ShareLink::class)
        ->and(array_keys(ShareLinkResource::getPages()))->toBe(['index', 'view']);
});

it('builds a table exposing token, target, abilities, expiry and access metadata', function () {
    expect(tableColumnNames(ShareLinkResource::class, ListShareLinks::class))
        ->toContain('token', 'item.title', 'folder.name', 'abilities', 'expires_at', 'access_count', 'revoked_at', 'created_at');
});

it('offers a revoke row action', function () {
    expect(tableActionNames(ShareLinkResource::class, ListShareLinks::class))
        ->toContain('revoke');
});

it('revokes a share link by setting revoked_at', function () {
    /** @var ShareLink $link */
    $link = ShareLink::factory()->create(['revoked_at' => null]);

    expect($link->revoked_at)->toBeNull();

    $link->update(['revoked_at' => now()]);

    expect($link->fresh()->revoked_at)->not->toBeNull();
});
