<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

/**
 * moveFolderTo() per spec §11.2 only updates path/depth on the moved
 * folder itself; descendants need a follow-up rebuildPaths() pass. This
 * test pins that behavior so consumers know to call rebuildPaths after
 * structural moves that may invalidate descendant paths.
 */
it('moves a folder but does not auto-update descendant paths', function (): void {
    $owner = StubUser::create(['email' => 'movetree@test.dev']);
    $library = app(MediaLibrary::class);

    $a = $library->createFolder($owner, 'A');
    $b = $library->createFolder($owner, 'B', $a);
    $c = $library->createFolder($owner, 'C', $b);

    expect($a->fresh()?->path)->toBe('/a');
    expect($b->fresh()?->path)->toBe('/a/b');
    expect($c->fresh()?->path)->toBe('/a/b/c');

    $d = $library->createFolder($owner, 'D');

    // Move B under D — the moved folder picks up the new path, but
    // descendant C is left with its stale "/a/b/c" string.
    $library->moveFolderTo($b, $d);

    expect($b->fresh()?->path)->toBe('/d/b');
    expect($b->fresh()?->depth)->toBe($d->depth + 1);
    expect($c->fresh()?->path)->toBe('/a/b/c');
});

it('rebuildPaths repairs descendant paths after a folder move', function (): void {
    $owner = StubUser::create(['email' => 'rebuild@test.dev']);
    $library = app(MediaLibrary::class);

    $a = $library->createFolder($owner, 'A');
    $b = $library->createFolder($owner, 'B', $a);
    $c = $library->createFolder($owner, 'C', $b);
    $d = $library->createFolder($owner, 'D');

    $library->moveFolderTo($b, $d);
    $library->rebuildPaths($owner);

    // After rebuildPaths, every folder is anchored at the right place.
    expect($a->fresh()?->path)->toBe('/a');
    expect($d->fresh()?->path)->toBe('/d');
    expect($b->fresh()?->path)->toBe('/d/b');
    expect($c->fresh()?->path)->toBe('/d/b/c');

    // Descendant counts should also reflect the new shape.
    expect(MediaLibraryFolder::query()->where('id', $b->id)->value('depth'))->toBe(1);
    expect(MediaLibraryFolder::query()->where('id', $c->id)->value('depth'))->toBe(2);
});
