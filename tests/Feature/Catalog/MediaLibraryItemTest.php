<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

it('persists translatable title across locales + slug', function () {
    $item = MediaLibraryItem::factory()->create([
        'title' => ['en' => 'Beach Sunset', 'tr' => 'Plaj Gun Batimi'],
    ]);

    expect($item->getTranslation('title', 'en'))->toBe('Beach Sunset');
    expect($item->getTranslation('title', 'tr'))->toBe('Plaj Gun Batimi');
    expect($item->slug)->not->toBeEmpty();
});

it('casts focal coordinates as floats and json metadata fields as arrays', function () {
    $item = MediaLibraryItem::factory()->create([
        'focal_x' => 0.25,
        'focal_y' => 0.75,
        'palette' => ['#ff0000', '#00ff00'],
        'exif' => ['Camera' => 'Pixel'],
        'metadata' => ['license' => 'cc-by'],
    ]);

    expect($item->focal_x)->toBe(0.25);
    expect($item->focal_y)->toBe(0.75);
    expect($item->palette)->toBe(['#ff0000', '#00ff00']);
    expect($item->exif)->toBe(['Camera' => 'Pixel']);
    expect($item->metadata)->toBe(['license' => 'cc-by']);
});

it('relates folder, storage, tags, attachments, share links', function () {
    $folder = MediaLibraryFolder::factory()->create();
    $tag = MediaLibraryTag::factory()->create();
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);
    $item->tags()->attach($tag);
    $link = ShareLink::factory()->create(['item_id' => $item->id]);

    expect($item->folder?->id)->toBe($folder->id);
    expect($item->storage)->not->toBeNull();
    expect($item->tags()->pluck('media_library_tags.id')->all())->toContain($tag->id);
    expect($item->shareLinks()->pluck('id')->all())->toContain($link->id);
});

it('url returns empty string when no spatie media attached', function () {
    $item = MediaLibraryItem::factory()->create();

    expect($item->url())->toBe('');
    expect($item->spatieMedia())->toBeNull();
});

it('variant() throws RuntimeException while VariantGenerator is unbound', function () {
    $item = MediaLibraryItem::factory()->create();

    expect(fn () => $item->variant(['width' => 100, 'height' => 100]))
        ->toThrow(RuntimeException::class, 'VariantGenerator not yet bound');
});

it('activeShares returns only non-revoked + not-yet-expired links', function () {
    $item = MediaLibraryItem::factory()->create();

    $active = ShareLink::factory()->create(['item_id' => $item->id, 'expires_at' => now()->addDay()]);
    ShareLink::factory()->revoked()->create(['item_id' => $item->id]);
    ShareLink::factory()->expired()->create(['item_id' => $item->id]);
    ShareLink::factory()->noExpiry()->create(['item_id' => $item->id]);

    $ids = $item->activeShares()->pluck('id')->all();

    expect($ids)->toContain($active->id);
    expect(count($ids))->toBe(2);
});

it('scopeByOwner filters by morph owner', function () {
    $user = StubUser::create(['email' => 'owner@test.dev']);
    MediaLibraryItem::factory()->create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->id,
    ]);
    MediaLibraryItem::factory()->create([
        'owner_type' => 'other_owner',
        'owner_id' => $user->id,
    ]);

    expect(MediaLibraryItem::query()->byOwner($user)->count())->toBe(1);
});

it('scopeByFolder filters non-recursively by default', function () {
    $folder = MediaLibraryFolder::factory()->create();
    MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);
    MediaLibraryItem::factory()->create();

    expect(MediaLibraryItem::query()->byFolder($folder)->count())->toBe(1);
});

it('scopeByFolder recursively matches descendant folders by path', function () {
    $root = MediaLibraryFolder::factory()->create(['path' => '/clients', 'slug' => 'clients']);
    $child = MediaLibraryFolder::factory()->create([
        'path' => '/clients/acme',
        'slug' => 'acme',
        'parent_id' => $root->id,
    ]);

    MediaLibraryItem::factory()->create(['folder_id' => $root->id]);
    MediaLibraryItem::factory()->create(['folder_id' => $child->id]);

    expect(MediaLibraryItem::query()->byFolder($root, recursive: true)->count())->toBe(2);
});

it('scopeByTag filters via pivot using model or slug', function () {
    $tag = MediaLibraryTag::factory()->create(['name' => ['en' => 'Sunsets']]);
    $item = MediaLibraryItem::factory()->create();
    $item->tags()->attach($tag);
    MediaLibraryItem::factory()->create();

    expect(MediaLibraryItem::query()->byTag($tag)->count())->toBe(1);
    expect(MediaLibraryItem::query()->byTag($tag->slug)->count())->toBe(1);
});

it('scopeByMimeType supports wildcards', function () {
    MediaLibraryItem::factory()->create(['mime_type' => 'image/jpeg']);
    MediaLibraryItem::factory()->create(['mime_type' => 'image/png']);
    MediaLibraryItem::factory()->create(['mime_type' => 'application/pdf']);

    expect(MediaLibraryItem::query()->byMimeType('image/*')->count())->toBe(2);
    expect(MediaLibraryItem::query()->byMimeType('image/jpeg')->count())->toBe(1);
});

it('scopeByDateRange filters by created_at range', function () {
    $old = MediaLibraryItem::factory()->create();
    $old->forceFill(['created_at' => now()->subDays(10)])->save();
    $recent = MediaLibraryItem::factory()->create();
    $recent->forceFill(['created_at' => now()->subDay()])->save();

    expect(MediaLibraryItem::query()->byDateRange(now()->subDays(3), now())->count())->toBe(1);
});

it('scopeSearch performs LIKE across textual fields', function () {
    MediaLibraryItem::factory()->create(['title' => ['en' => 'Autumn beach']]);
    MediaLibraryItem::factory()->create(['title' => ['en' => 'Winter forest']]);

    expect(MediaLibraryItem::query()->search('beach')->count())->toBe(1);
});
