<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubPost;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
});

it('keeps attachments stable after a replace and writes a version row', function (): void {
    $owner = StubUser::create(['email' => 'replace@test.dev']);
    $library = app(MediaLibrary::class);

    $item = $library->upload(
        new UploadedFile(__DIR__.'/../../fixtures/test.png', 'original.png', 'image/png', null, true),
        $owner,
    );
    $itemId = $item->id;

    // Attach the item to three different posts.
    $posts = [];
    for ($i = 0; $i < 3; $i++) {
        $post = StubPost::create(['title' => "post-{$i}"]);
        $post->attachMediaItem($item, role: 'cover');
        $posts[] = $post;
    }

    // Sanity: every post sees the same item id.
    foreach ($posts as $post) {
        expect($post->coverItem()?->id)->toBe($itemId);
    }

    expect(MediaLibraryVersion::query()->where('item_id', $itemId)->count())->toBe(0);

    // Replace the underlying file. The item id should remain stable.
    $replacement = new UploadedFile(__DIR__.'/../../fixtures/test.jpg', 'updated.jpg', 'image/jpeg', null, true);
    $replaced = $library->replace($item, $replacement, 'swap source file');

    expect($replaced->id)->toBe($itemId);
    expect($replaced->filename)->toBe('updated.jpg');
    expect($replaced->mime_type)->toBe('image/jpeg');

    // All three posts still resolve to the same (now-updated) item.
    foreach ($posts as $post) {
        $cover = $post->fresh()?->coverItem();
        expect($cover)->not->toBeNull();
        expect($cover?->id)->toBe($itemId);
        expect($cover?->filename)->toBe('updated.jpg');
    }

    // A version row captured the previous file.
    $versions = MediaLibraryVersion::query()->where('item_id', $itemId)->get();
    expect($versions)->toHaveCount(1);
    expect($versions->first()?->filename)->toBe('original.png');
});
