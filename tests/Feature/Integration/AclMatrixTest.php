<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

/**
 * Detailed ACL behavior is pinned by tests/Feature/Access/MediaLibraryAccessTest.
 * This file adds a thin end-to-end smoke that the Gate-attached policies
 * resolve the same answer via the standard policy entrypoints the provider
 * wires up.
 */
it('Gate::allows("view", $publicItem) returns true for guests and authed users', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Public]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    $user = StubUser::create(['email' => 'view@test.dev']);

    expect(Gate::forUser(null)->allows('view', $item))->toBeTrue();
    expect(Gate::forUser($user)->allows('view', $item))->toBeTrue();
});

it('Gate::denies("view", $privateItem) for an unrelated user', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Private]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    $stranger = StubUser::create(['email' => 'stranger@test.dev']);

    expect(Gate::forUser($stranger)->allows('view', $item))->toBeFalse();
});

it('Gate::allows("manage", $orphanItem) only for the owner', function (): void {
    $owner = StubUser::create(['email' => 'owner@test.dev']);
    $stranger = StubUser::create(['email' => 'someone-else@test.dev']);

    $item = MediaLibraryItem::factory()->create([
        'folder_id' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    expect(Gate::forUser($owner)->allows('manage', $item))->toBeTrue();
    expect(Gate::forUser($stranger)->allows('manage', $item))->toBeFalse();
});
