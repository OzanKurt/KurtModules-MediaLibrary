<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderCreated;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderDeleted;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Observers\MediaLibraryFolderObserver;

beforeEach(function (): void {
    MediaLibraryFolder::observe(MediaLibraryFolderObserver::class);
});

afterEach(function (): void {
    MediaLibraryFolder::flushEventListeners();
});

it('fills path + depth for a root folder when path is empty', function (): void {
    $folder = MediaLibraryFolder::factory()->create([
        'name' => ['en' => 'Marketing'],
        'slug' => 'marketing',
        'path' => '',
        'depth' => 0,
        'parent_id' => null,
    ]);

    expect($folder->path)->toBe('/marketing');
    expect($folder->depth)->toBe(0);
});

it('fills path + depth for a nested folder using parent context', function (): void {
    $parent = MediaLibraryFolder::factory()->create([
        'name' => ['en' => 'Parent'],
        'slug' => 'parent',
        'path' => '/parent',
        'depth' => 0,
        'parent_id' => null,
    ]);

    $child = MediaLibraryFolder::factory()->create([
        'name' => ['en' => 'Child'],
        'slug' => 'child',
        'path' => '',
        'depth' => 0,
        'parent_id' => $parent->id,
    ]);

    expect($child->path)->toBe('/parent/child');
    expect($child->depth)->toBe(1);
});

it('dispatches FolderCreated on created', function (): void {
    Event::fake([FolderCreated::class]);

    $folder = MediaLibraryFolder::factory()->create();

    Event::assertDispatched(
        FolderCreated::class,
        fn (FolderCreated $event): bool => $event->folder->is($folder),
    );
});

it('dispatches FolderDeleted on deleted', function (): void {
    $folder = MediaLibraryFolder::factory()->create();

    Event::fake([FolderDeleted::class]);

    $folder->delete();

    Event::assertDispatched(
        FolderDeleted::class,
        fn (FolderDeleted $event): bool => $event->folder->is($folder),
    );
});
