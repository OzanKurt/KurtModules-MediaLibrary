<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Console\Commands\RebuildPathsCommand;

beforeEach(function (): void {
    Artisan::registerCommand(new RebuildPathsCommand);
});

it('restores corrupted folder paths by walking the parent chain', function (): void {
    $root = MediaLibraryFolder::factory()->create([
        'owner_id' => 99,
        'slug' => 'root',
        'name' => ['en' => 'Root'],
        'path' => '/root',
        'depth' => 0,
    ]);

    $child = MediaLibraryFolder::factory()->create([
        'owner_id' => 99,
        'parent_id' => $root->id,
        'slug' => 'child',
        'name' => ['en' => 'Child'],
        'path' => '/root/child',
        'depth' => 1,
    ]);

    $grandchild = MediaLibraryFolder::factory()->create([
        'owner_id' => 99,
        'parent_id' => $child->id,
        'slug' => 'grand',
        'name' => ['en' => 'Grand'],
        'path' => '/root/child/grand',
        'depth' => 2,
    ]);

    // Corrupt the paths + depths
    $child->forceFill(['path' => '/garbage', 'depth' => 99])->save();
    $grandchild->forceFill(['path' => '/garbage/grand', 'depth' => 99])->save();
    $root->forceFill(['path' => '/wrong', 'depth' => 5])->save();

    $exit = Artisan::call('media-library:rebuild-paths');

    expect($exit)->toBe(0);
    expect($root->fresh()?->path)->toBe('/root');
    expect($root->fresh()?->depth)->toBe(0);
    expect($child->fresh()?->path)->toBe('/root/child');
    expect($child->fresh()?->depth)->toBe(1);
    expect($grandchild->fresh()?->path)->toBe('/root/child/grand');
    expect($grandchild->fresh()?->depth)->toBe(2);
});

it('scopes the rebuild to a single owner when an owner id is given', function (): void {
    $a = MediaLibraryFolder::factory()->create([
        'owner_id' => 10,
        'slug' => 'a',
        'name' => ['en' => 'A'],
        'path' => '/wrong-a',
        'depth' => 9,
    ]);

    $b = MediaLibraryFolder::factory()->create([
        'owner_id' => 20,
        'slug' => 'b',
        'name' => ['en' => 'B'],
        'path' => '/wrong-b',
        'depth' => 9,
    ]);

    $exit = Artisan::call('media-library:rebuild-paths', ['owner' => 10]);

    expect($exit)->toBe(0);
    expect($a->fresh()?->path)->toBe('/a');
    expect($a->fresh()?->depth)->toBe(0);
    expect($b->fresh()?->path)->toBe('/wrong-b');
    expect($b->fresh()?->depth)->toBe(9);
});
