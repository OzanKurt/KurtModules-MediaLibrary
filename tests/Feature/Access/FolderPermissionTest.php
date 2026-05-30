<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

it('persists a folder permission row with cast enums + boolean cascade', function () {
    $folder = MediaLibraryFolder::factory()->create();
    $perm = FolderPermission::factory()->create([
        'folder_id' => $folder->id,
        'subject_type' => SubjectType::User,
        'subject_value' => '42',
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    expect($perm->subject_type)->toBe(SubjectType::User);
    expect($perm->subject_value)->toBe('42');
    expect($perm->capability)->toBe(Capability::Download);
    expect($perm->cascade)->toBeFalse();
    expect($perm->folder?->id)->toBe($folder->id);
});

it('forUser state factory writes user subject row', function () {
    $perm = FolderPermission::factory()->forUser(7, Capability::Manage)->create();

    expect($perm->subject_type)->toBe(SubjectType::User);
    expect($perm->subject_value)->toBe('7');
    expect($perm->capability)->toBe(Capability::Manage);
});

it('forRole state factory writes role subject row', function () {
    $perm = FolderPermission::factory()->forRole('editor', Capability::View)->create();

    expect($perm->subject_type)->toBe(SubjectType::Role);
    expect($perm->subject_value)->toBe('editor');
});
