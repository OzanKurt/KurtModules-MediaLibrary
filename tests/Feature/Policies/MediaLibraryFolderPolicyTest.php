<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\FolderPermissionResolver;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Policies\MediaLibraryFolderPolicy;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->app->bind(MediaSubjectResolver::class, DefaultSubjectResolver::class);
    $this->app->singleton(FolderPermissionResolver::class, fn ($app) => new FolderPermissionResolver(
        $app->make(MediaSubjectResolver::class),
    ));
    $this->app->singleton(MediaLibraryAccess::class, fn ($app) => new MediaLibraryAccess(
        $app->make(FolderPermissionResolver::class),
    ));

    $this->policy = new MediaLibraryFolderPolicy($this->app->make(MediaLibraryAccess::class));
});

function folderPolicyStubUser(int $id): StubUser
{
    $user = new StubUser;
    $user->setRawAttributes(['id' => $id], sync: true);
    $user->exists = true;

    return $user;
}

it('grants view on a public folder to guests', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Public]);

    expect($this->policy->view(null, $folder))->toBeTrue();
});

it('denies view on a restricted folder for an unrelated user', function (): void {
    $other = folderPolicyStubUser(99);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);

    expect($this->policy->view($other, $folder))->toBeFalse();
});

it('grants manage on a folder when an ACL row matches with Manage capability', function (): void {
    $user = folderPolicyStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Manage,
        'cascade' => true,
    ]);

    expect($this->policy->manage($user, $folder))->toBeTrue();
});

it('denies manage when ACL row only grants Download', function (): void {
    $user = folderPolicyStubUser(7);
    $folder = MediaLibraryFolder::factory()->create(['visibility' => Visibility::Restricted]);
    FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '7',
        'capability' => Capability::Download,
        'cascade' => true,
    ]);

    expect($this->policy->manage($user, $folder))->toBeFalse();
    expect($this->policy->download($user, $folder))->toBeTrue();
});
