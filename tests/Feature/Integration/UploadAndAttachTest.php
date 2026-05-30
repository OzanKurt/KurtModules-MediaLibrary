<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubPost;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
});

it('uploads a file then attaches it to a consumer model via HasMediaLibraryItems', function (): void {
    $owner = StubUser::create(['email' => 'cover@test.dev']);

    $file = new UploadedFile(
        __DIR__.'/../../fixtures/test.png',
        'cover.png',
        'image/png',
        null,
        true,
    );

    $library = app(MediaLibrary::class);
    $item = $library->upload($file, $owner);

    expect($item)->toBeInstanceOf(MediaLibraryItem::class);
    expect($item->owner_id)->toBe($owner->getKey());

    $post = StubPost::create(['title' => 'hello world']);
    $attachment = $post->attachMediaItem($item, role: 'cover');

    expect($attachment->role)->toBe('cover');
    expect($post->mediaItems('cover')->count())->toBe(1);
    expect($post->coverItem()?->id)->toBe($item->id);
});

it('attaches multiple items to a single post in distinct roles', function (): void {
    $owner = StubUser::create(['email' => 'multi@test.dev']);
    $library = app(MediaLibrary::class);

    $cover = $library->upload(
        new UploadedFile(__DIR__.'/../../fixtures/test.png', 'cover.png', 'image/png', null, true),
        $owner,
    );
    $social = $library->upload(
        new UploadedFile(__DIR__.'/../../fixtures/test.png', 'social.png', 'image/png', null, true),
        $owner,
    );

    $post = StubPost::create(['title' => 'multi-attach']);
    $post->attachMediaItem($cover, role: 'cover');
    $post->attachMediaItem($social, role: 'social');

    expect($post->mediaItems()->count())->toBe(2);
    expect($post->coverItem()?->id)->toBe($cover->id);
    expect($post->socialItem()?->id)->toBe($social->id);
});
