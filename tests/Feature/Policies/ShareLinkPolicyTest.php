<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\FolderPermissionResolver;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Policies\ShareLinkPolicy;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->app->bind(MediaSubjectResolver::class, DefaultSubjectResolver::class);
    $this->app->singleton(FolderPermissionResolver::class, fn ($app) => new FolderPermissionResolver(
        $app->make(MediaSubjectResolver::class),
    ));
    $this->app->singleton(MediaLibraryAccess::class, fn ($app) => new MediaLibraryAccess(
        $app->make(FolderPermissionResolver::class),
    ));

    $this->policy = new ShareLinkPolicy($this->app->make(MediaLibraryAccess::class));
});

function shareLinkPolicyStubUser(int $id): StubUser
{
    $user = new StubUser;
    $user->setRawAttributes(['id' => $id], sync: true);
    $user->exists = true;

    return $user;
}

it('allows revoke for the share link creator', function (): void {
    $creator = shareLinkPolicyStubUser(42);
    $item = MediaLibraryItem::factory()->create();
    $link = ShareLink::factory()->create([
        'item_id' => $item->id,
        'created_by' => 42,
    ]);

    expect($this->policy->revoke($creator, $link))->toBeTrue();
});

it('denies revoke for an unrelated user with no manage access', function (): void {
    $other = shareLinkPolicyStubUser(99);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Private, 'owner_id' => 42]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);
    $link = ShareLink::factory()->create([
        'item_id' => $item->id,
        'created_by' => 42,
    ]);

    expect($this->policy->revoke($other, $link))->toBeFalse();
});

it('allows create when the user can manage the target', function (): void {
    $owner = shareLinkPolicyStubUser(42);
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

    expect($this->policy->create($owner, $item))->toBeTrue();
});

it('denies create when the user cannot manage the target', function (): void {
    $other = shareLinkPolicyStubUser(99);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Public]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    expect($this->policy->create($other, $item))->toBeFalse();
});
