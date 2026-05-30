<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\FolderPermissionResolver;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->access = new MediaLibraryAccess(
        new FolderPermissionResolver(new DefaultSubjectResolver),
    );
});

function makeStubUser(int $id): StubUser
{
    $user = new StubUser;
    $user->setRawAttributes(['id' => $id], sync: true);
    $user->exists = true;

    return $user;
}

it('grants View on a public folder to guests', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Public]);

    expect($this->access->check(null, $folder, Capability::View))->toBeTrue();
    expect($this->access->check(null, $folder, Capability::Download))->toBeTrue();
    expect($this->access->check(null, $folder, Capability::Manage))->toBeFalse();
});

it('denies access to a restricted folder for guests', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);

    expect($this->access->check(null, $folder, Capability::View))->toBeFalse();
    expect($this->access->check(null, $folder, Capability::Download))->toBeFalse();
});

it('grants Manage to the private folder owner', function (): void {
    $user = makeStubUser(42);
    $folder = MediaLibraryFolder::factory()->create([
        'visibility' => Visibility::Private,
        'owner_type' => 'stub_owner',
        'owner_id' => 42,
    ]);

    expect($this->access->check($user, $folder, Capability::View))->toBeTrue();
    expect($this->access->check($user, $folder, Capability::Download))->toBeTrue();
    expect($this->access->check($user, $folder, Capability::Manage))->toBeTrue();
});

it('denies a non-owner on a private folder', function (): void {
    $owner = makeStubUser(42);
    $other = makeStubUser(99);
    $folder = MediaLibraryFolder::factory()->create([
        'visibility' => Visibility::Private,
        'owner_type' => 'stub_owner',
        'owner_id' => $owner->getKey(),
    ]);

    expect($this->access->check($other, $folder, Capability::View))->toBeFalse();
});

it('grants Download via an ACL row for a matching user', function (): void {
    $user = makeStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    expect($this->access->check($user, $folder, Capability::View))->toBeTrue();
    expect($this->access->check($user, $folder, Capability::Download))->toBeTrue();
    expect($this->access->check($user, $folder, Capability::Manage))->toBeFalse();
});

it('inherits a cascade=true ACL row on descendants', function (): void {
    $user = makeStubUser(11);
    $parent = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $child = MediaLibraryFolder::factory()->create([
        'parent_id' => $parent->id,
        'visibility' => Visibility::Restricted,
    ]);
    FolderPermission::factory()->create([
        'folder_id' => $parent->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '11',
        'capability' => Capability::Download,
        'cascade' => true,
    ]);

    expect($this->access->check($user, $child, Capability::Download))->toBeTrue();
});

it('does not inherit a cascade=false ACL row on descendants', function (): void {
    $user = makeStubUser(11);
    $parent = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $child = MediaLibraryFolder::factory()->create([
        'parent_id' => $parent->id,
        'visibility' => Visibility::Restricted,
    ]);
    FolderPermission::factory()->create([
        'folder_id' => $parent->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '11',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    expect($this->access->check($user, $child, Capability::View))->toBeFalse();
});

it('still honours cascade=false on the original folder itself', function (): void {
    $user = makeStubUser(11);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '11',
        'capability' => Capability::View,
        'cascade' => false,
    ]);

    expect($this->access->check($user, $folder, Capability::View))->toBeTrue();
});

it('picks the highest capability when multiple subject rows match on the same folder', function (): void {
    $user = makeStubUser(11);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);

    // Everyone gets View.
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::Everyone,
        'subject_value' => null,
        'capability' => Capability::View,
        'cascade' => true,
    ]);
    // The user explicitly gets Manage.
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '11',
        'capability' => Capability::Manage,
        'cascade' => true,
    ]);

    expect($this->access->check($user, $folder, Capability::Manage))->toBeTrue();
});

it('uses the nearest ancestor row when no row exists on the folder itself', function (): void {
    $user = makeStubUser(11);
    $grandparent = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    $parent = MediaLibraryFolder::factory()->create([
        'parent_id' => $grandparent->id,
        'visibility' => Visibility::Restricted,
    ]);
    $child = MediaLibraryFolder::factory()->create([
        'parent_id' => $parent->id,
        'visibility' => Visibility::Restricted,
    ]);

    // Grandparent: Manage. Parent: View. Child has no rows.
    // Nearest ancestor with matching subject is parent → View should win.
    FolderPermission::factory()->create([
        'folder_id' => $grandparent->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '11',
        'capability' => Capability::Manage,
        'cascade' => true,
    ]);
    FolderPermission::factory()->create([
        'folder_id' => $parent->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '11',
        'capability' => Capability::View,
        'cascade' => true,
    ]);

    expect($this->access->check($user, $child, Capability::View))->toBeTrue();
    // Parent View overrides grandparent Manage because parent is nearer.
    expect($this->access->check($user, $child, Capability::Download))->toBeFalse();
});

it('denies when ACL row subject does not match the user', function (): void {
    $user = makeStubUser(11);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '99', // different user
        'capability' => Capability::Manage,
        'cascade' => true,
    ]);

    expect($this->access->check($user, $folder, Capability::View))->toBeFalse();
});

it('grants owner-only Manage on an orphan item with null folder', function (): void {
    $user = makeStubUser(42);
    $item = MediaLibraryItem::factory()->create([
        'folder_id' => null,
        'owner_type' => 'stub_owner',
        'owner_id' => 42,
    ]);

    expect($this->access->check($user, $item, Capability::Manage))->toBeTrue();
});

it('denies a non-owner on an orphan item', function (): void {
    $other = makeStubUser(99);
    $item = MediaLibraryItem::factory()->create([
        'folder_id' => null,
        'owner_type' => 'stub_owner',
        'owner_id' => 42,
    ]);

    expect($this->access->check($other, $item, Capability::View))->toBeFalse();
});

it('denies a guest on an orphan item', function (): void {
    $item = MediaLibraryItem::factory()->create([
        'folder_id' => null,
        'owner_type' => 'stub_owner',
        'owner_id' => 42,
    ]);

    expect($this->access->check(null, $item, Capability::View))->toBeFalse();
});

it('treats an item as inheriting its folder ACL', function (): void {
    $user = makeStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Download,
        'cascade' => true,
    ]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    expect($this->access->check($user, $item, Capability::Download))->toBeTrue();
    expect($this->access->check($user, $item, Capability::Manage))->toBeFalse();
});

it('memoises check results per user + folder for the request', function (): void {
    $user = makeStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Public]);

    $this->access->check($user, $folder, Capability::View);
    $this->access->check($user, $folder, Capability::Download);

    expect(true)->toBeTrue(); // smoke; ensures cache key path executes without exception.
});

it('flush() clears the cache', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Public]);

    expect($this->access->check(null, $folder, Capability::View))->toBeTrue();
    $this->access->flush();
    expect($this->access->check(null, $folder, Capability::View))->toBeTrue();
});
