<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

it('creates a folder for the authenticated owner', function (): void {
    $user = StubUser::create(['email' => 'owner@test.dev']);

    $response = $this->actingAs($user)->postJson('/api/media/folders', [
        'name' => 'Campaign Assets',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Campaign Assets');

    $folder = MediaLibraryFolder::query()->first();
    expect($folder)->not->toBeNull();
    expect((int) $folder?->owner_id)->toBe((int) $user->getKey());
});

it('blocks a guest from creating a folder', function (): void {
    $this->postJson('/api/media/folders', ['name' => 'Nope'])->assertUnauthorized();
});

it('shows a folder the caller may view', function (): void {
    $user = StubUser::create(['email' => 'viewer@test.dev']);
    $folder = MediaLibraryFolder::factory()->create([
        'owner_type' => 'stub_owner',
        'owner_id' => 500,
        'visibility' => Visibility::Public,
    ]);

    $this->actingAs($user)
        ->getJson("/api/media/folders/{$folder->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $folder->id);
});

it('returns 403 for a folder the caller cannot access (ACL denial)', function (): void {
    $user = StubUser::create(['email' => 'stranger@test.dev']);
    $folder = MediaLibraryFolder::factory()->create([
        'owner_type' => 'stub_owner',
        'owner_id' => 500,
        'visibility' => Visibility::Restricted,
    ]);

    $this->actingAs($user)
        ->getJson("/api/media/folders/{$folder->id}")
        ->assertForbidden();
});

it('lists only ACL-scoped children under a parent', function (): void {
    $user = StubUser::create(['email' => 'acl@test.dev']);

    // Parent is visible to the user via a NON-cascading View grant, so the grant
    // does not reach the children.
    $parent = MediaLibraryFolder::factory()->create([
        'owner_type' => 'stub_owner',
        'owner_id' => 500,
        'visibility' => Visibility::Restricted,
    ]);
    FolderPermission::factory()->create([
        'folder_id' => $parent->id,
        'subject_type' => SubjectType::User,
        'subject_value' => (string) $user->getKey(),
        'capability' => Capability::View,
        'cascade' => false,
    ]);

    $hidden = MediaLibraryFolder::factory()->create([
        'parent_id' => $parent->id,
        'visibility' => Visibility::Restricted,
    ]);
    $visible = MediaLibraryFolder::factory()->create([
        'parent_id' => $parent->id,
        'visibility' => Visibility::Public,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/media/folders?parent={$parent->id}")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($visible->id);
    expect($ids)->not->toContain($hidden->id);
});

it('updates and moves a folder', function (): void {
    $user = StubUser::create(['email' => 'mover@test.dev']);

    $newParent = app(MediaLibrary::class)->createFolder($user, 'Parent');
    $folder = app(MediaLibrary::class)->createFolder($user, 'Child');

    $this->actingAs($user)
        ->patchJson("/api/media/folders/{$folder->id}", [
            'name' => 'Renamed Child',
            'parent_id' => $newParent->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Child')
        ->assertJsonPath('data.parent_id', $newParent->id);

    expect($folder->fresh()?->parent_id)->toBe($newParent->id);
});

it('soft-deletes a folder', function (): void {
    $user = StubUser::create(['email' => 'trasher@test.dev']);
    $folder = app(MediaLibrary::class)->createFolder($user, 'Doomed');

    $this->actingAs($user)
        ->deleteJson("/api/media/folders/{$folder->id}")
        ->assertNoContent();

    expect(MediaLibraryFolder::query()->find($folder->id))->toBeNull();
    expect(MediaLibraryFolder::withTrashed()->find($folder->id))->not->toBeNull();
});

it('shares a folder as an ACL grant', function (): void {
    $user = StubUser::create(['email' => 'granter@test.dev']);
    $folder = app(MediaLibrary::class)->createFolder($user, 'Shared');

    $this->actingAs($user)
        ->postJson("/api/media/folders/{$folder->id}/share", [
            'subject_type' => 'user',
            'subject_value' => '42',
            'capability' => 'download',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'acl_grant');

    expect(FolderPermission::query()->where('folder_id', $folder->id)->where('subject_value', '42')->exists())->toBeTrue();
});

it('shares a folder as a bearer share-link', function (): void {
    $user = StubUser::create(['email' => 'linker@test.dev']);
    $folder = app(MediaLibrary::class)->createFolder($user, 'LinkShared');

    $this->actingAs($user)
        ->postJson("/api/media/folders/{$folder->id}/share", [
            'abilities' => ['view', 'download'],
            'expires_in' => 3600,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'share_link');

    expect(ShareLink::query()->where('folder_id', $folder->id)->exists())->toBeTrue();
});
