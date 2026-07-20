<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Jobs\ExtractMediaMetadata;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

function apiFixture(string $name = 'upload.png'): UploadedFile
{
    return new UploadedFile(__DIR__.'/../fixtures/test.png', $name, 'image/png', null, true);
}

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
});

it('uploads an image through the API and dispatches the extractor job', function (): void {
    Queue::fake([ExtractMediaMetadata::class]);

    $user = StubUser::create(['email' => 'uploader@test.dev']);

    $response = $this->actingAs($user)->postJson('/api/media/items', [
        'file' => apiFixture('photo.png'),
        'title' => 'Hero Shot',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.title', 'Hero Shot');

    $item = MediaLibraryItem::query()->first();
    expect($item)->not->toBeNull();
    expect((int) $item?->owner_id)->toBe((int) $user->getKey());
    expect($item?->spatieMedia())->not->toBeNull();

    // Uploads go THROUGH the UploadCoordinator, so the async pipeline is queued.
    Queue::assertPushed(ExtractMediaMetadata::class);
});

it('blocks a guest from uploading', function (): void {
    $this->postJson('/api/media/items', ['file' => apiFixture()])->assertUnauthorized();
});

it('shows an item the caller may view', function (): void {
    $user = StubUser::create(['email' => 'itemviewer@test.dev']);
    $folder = app(MediaLibrary::class)->createFolder($user, 'Visible');
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    $this->actingAs($user)
        ->getJson("/api/media/items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $item->id);
});

it('lists items scoped to a folder the caller may view', function (): void {
    $user = StubUser::create(['email' => 'lister@test.dev']);
    $folder = app(MediaLibrary::class)->createFolder($user, 'Bucket');
    MediaLibraryItem::factory()->count(2)->create(['folder_id' => $folder->id]);

    $this->actingAs($user)
        ->getJson("/api/media/items?folder={$folder->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns 403 listing items in a folder the caller cannot access', function (): void {
    $user = StubUser::create(['email' => 'nolist@test.dev']);
    $folder = MediaLibraryFolder::factory()->create([
        'owner_type' => 'stub_owner',
        'owner_id' => 999,
        'visibility' => Visibility::Restricted,
    ]);
    MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    $this->actingAs($user)
        ->getJson("/api/media/items?folder={$folder->id}")
        ->assertForbidden();
});

it('returns 403 showing an item in a restricted folder', function (): void {
    $user = StubUser::create(['email' => 'noshow@test.dev']);
    $folder = MediaLibraryFolder::factory()->create([
        'owner_type' => 'stub_owner',
        'owner_id' => 999,
        'visibility' => Visibility::Restricted,
    ]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    $this->actingAs($user)
        ->getJson("/api/media/items/{$item->id}")
        ->assertForbidden();
});

it('updates and moves an item', function (): void {
    $user = StubUser::create(['email' => 'itemmover@test.dev']);
    $source = app(MediaLibrary::class)->createFolder($user, 'Source');
    $target = app(MediaLibrary::class)->createFolder($user, 'Target');
    $item = MediaLibraryItem::factory()->create(['folder_id' => $source->id]);

    $this->actingAs($user)
        ->patchJson("/api/media/items/{$item->id}", [
            'title' => 'Moved & Renamed',
            'folder_id' => $target->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.folder_id', $target->id)
        ->assertJsonPath('data.title', 'Moved & Renamed');

    expect($item->fresh()?->folder_id)->toBe($target->id);
});

it('replaces an item file through the coordinator', function (): void {
    Queue::fake([ExtractMediaMetadata::class]);

    $user = StubUser::create(['email' => 'replacer@test.dev']);
    $item = app(MediaLibrary::class)->upload(apiFixture('original.png'), $user);

    $this->actingAs($user)
        ->postJson("/api/media/items/{$item->id}/replace", [
            'file' => apiFixture('replacement.png'),
            'changelog' => 'new crop',
        ])
        ->assertOk();

    expect(MediaLibraryVersion::query()->where('item_id', $item->id)->count())->toBe(1);
});

it('soft-deletes an item', function (): void {
    $user = StubUser::create(['email' => 'itemtrasher@test.dev']);
    $item = app(MediaLibrary::class)->upload(apiFixture('doomed.png'), $user);

    $this->actingAs($user)
        ->deleteJson("/api/media/items/{$item->id}")
        ->assertNoContent();

    expect(MediaLibraryItem::query()->find($item->id))->toBeNull();
    expect(MediaLibraryItem::withTrashed()->find($item->id))->not->toBeNull();
});

it('issues a signed URL that downloads the bytes without auth', function (): void {
    Queue::fake([ExtractMediaMetadata::class]);

    $user = StubUser::create(['email' => 'signer@test.dev']);
    $item = app(MediaLibrary::class)->upload(apiFixture('signed.png'), $user);

    $signed = $this->actingAs($user)
        ->getJson("/api/media/items/{$item->id}/signed-url")
        ->assertOk()
        ->json('data.url');

    expect($signed)->toBeString();

    // A brand-new guest request (no auth) succeeds purely on the signature.
    $this->get($signed)->assertOk();
});

it('downloads an item for a caller with ACL download rights', function (): void {
    Queue::fake([ExtractMediaMetadata::class]);

    $user = StubUser::create(['email' => 'downloader@test.dev']);
    $item = app(MediaLibrary::class)->upload(apiFixture('dl.png'), $user);

    $this->actingAs($user)
        ->get("/api/media/items/{$item->id}/download")
        ->assertOk();

    expect($item->fresh()?->download_count)->toBe(1);
});
