<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\FolderPermissionResolver;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Policies\MediaLibraryItemPolicy;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->app->bind(MediaSubjectResolver::class, DefaultSubjectResolver::class);
    $this->app->singleton(FolderPermissionResolver::class, fn ($app) => new FolderPermissionResolver(
        $app->make(MediaSubjectResolver::class),
    ));
    $this->app->singleton(MediaLibraryAccess::class, fn ($app) => new MediaLibraryAccess(
        $app->make(FolderPermissionResolver::class),
    ));

    $this->policy = new MediaLibraryItemPolicy($this->app->make(MediaLibraryAccess::class));
});

function policyStubUser(int $id): StubUser
{
    $user = new StubUser;
    $user->setRawAttributes(['id' => $id], sync: true);
    $user->exists = true;

    return $user;
}

it('grants view on an item inside a public folder', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Public]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    expect($this->policy->view(null, $item))->toBeTrue();
});

it('denies manage on a private folder item for a non-owner', function (): void {
    $other = policyStubUser(99);
    $folder = MediaLibraryFolder::factory()->create([
        'visibility' => Visibility::Private,
        'owner_type' => 'stub_owner',
        'owner_id' => 42,
    ]);
    $item = MediaLibraryItem::factory()->create([
        'folder_id' => $folder->id,
        'owner_type' => 'stub_owner',
        'owner_id' => 42,
    ]);

    expect($this->policy->manage($other, $item))->toBeFalse();
});

it('grants manage to the private folder owner', function (): void {
    $owner = policyStubUser(42);
    $folder = MediaLibraryFolder::factory()->create([
        'visibility' => Visibility::Private,
        'owner_type' => 'stub_owner',
        'owner_id' => 42,
    ]);
    $item = MediaLibraryItem::factory()->create([
        'folder_id' => $folder->id,
        'owner_type' => 'stub_owner',
        'owner_id' => 42,
    ]);

    expect($this->policy->manage($owner, $item))->toBeTrue();
});

it('denies download on a restricted folder item for a guest', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    expect($this->policy->download(null, $item))->toBeFalse();
});
