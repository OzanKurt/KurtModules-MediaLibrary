<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Exceptions\SelfReferentialFolder;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

/**
 * moveFolderTo() cascades path/depth to the moved folder AND every
 * descendant in a single pass, so consumers never observe stale
 * descendant paths after a structural move.
 */
it('moves a folder and cascades path/depth to all descendants', function (): void {
    $owner = StubUser::create(['email' => 'movetree@test.dev']);
    $library = app(MediaLibrary::class);

    $a = $library->createFolder($owner, 'A');
    $b = $library->createFolder($owner, 'B', $a);
    $c = $library->createFolder($owner, 'C', $b);

    expect($a->fresh()?->path)->toBe('/a');
    expect($b->fresh()?->path)->toBe('/a/b');
    expect($c->fresh()?->path)->toBe('/a/b/c');

    $d = $library->createFolder($owner, 'D');

    // Move B under D — the moved folder AND descendant C are both
    // immediately re-anchored, with no follow-up rebuild required.
    $library->moveFolderTo($b, $d);

    expect($b->fresh()?->path)->toBe('/d/b');
    expect($b->fresh()?->depth)->toBe($d->depth + 1);
    expect($c->fresh()?->path)->toBe('/d/b/c');
    expect($c->fresh()?->depth)->toBe($d->depth + 2);
});

it('cascades paths across multiple descendant levels and siblings', function (): void {
    $owner = StubUser::create(['email' => 'deeptree@test.dev']);
    $library = app(MediaLibrary::class);

    $a = $library->createFolder($owner, 'A');
    $b = $library->createFolder($owner, 'B', $a);
    $c1 = $library->createFolder($owner, 'C1', $b);
    $c2 = $library->createFolder($owner, 'C2', $b);
    $d = $library->createFolder($owner, 'D', $c1);

    $target = $library->createFolder($owner, 'Target');

    $library->moveFolderTo($b, $target);

    expect($b->fresh()?->path)->toBe('/target/b');
    expect($c1->fresh()?->path)->toBe('/target/b/c1');
    expect($c2->fresh()?->path)->toBe('/target/b/c2');
    expect($d->fresh()?->path)->toBe('/target/b/c1/d');
    expect($d->fresh()?->depth)->toBe(3);
});

it('moves a folder to the root and cascades descendant paths', function (): void {
    $owner = StubUser::create(['email' => 'toroot@test.dev']);
    $library = app(MediaLibrary::class);

    $a = $library->createFolder($owner, 'A');
    $b = $library->createFolder($owner, 'B', $a);
    $c = $library->createFolder($owner, 'C', $b);

    // Move B to the root (null parent).
    $library->moveFolderTo($b, null);

    expect($b->fresh()?->path)->toBe('/b');
    expect($b->fresh()?->depth)->toBe(0);
    expect($c->fresh()?->path)->toBe('/b/c');
    expect($c->fresh()?->depth)->toBe(1);
});

it('rejects moving a folder into its own descendant', function (): void {
    $owner = StubUser::create(['email' => 'cycle@test.dev']);
    $library = app(MediaLibrary::class);

    $a = $library->createFolder($owner, 'A');
    $b = $library->createFolder($owner, 'B', $a);
    $c = $library->createFolder($owner, 'C', $b);

    // Moving A under its own grandchild C must be rejected.
    expect(fn () => $library->moveFolderTo($a, $c->fresh()))
        ->toThrow(SelfReferentialFolder::class);

    // Tree is untouched.
    expect($a->fresh()?->path)->toBe('/a');
    expect($b->fresh()?->path)->toBe('/a/b');
    expect($c->fresh()?->path)->toBe('/a/b/c');
});

it('rejects moving a folder onto itself', function (): void {
    $owner = StubUser::create(['email' => 'self@test.dev']);
    $library = app(MediaLibrary::class);

    $a = $library->createFolder($owner, 'A');

    expect(fn () => $library->moveFolderTo($a, $a))
        ->toThrow(SelfReferentialFolder::class);
});

it('rebuildPaths remains a valid maintenance pass after a folder move', function (): void {
    $owner = StubUser::create(['email' => 'rebuild@test.dev']);
    $library = app(MediaLibrary::class);

    $a = $library->createFolder($owner, 'A');
    $b = $library->createFolder($owner, 'B', $a);
    $c = $library->createFolder($owner, 'C', $b);
    $d = $library->createFolder($owner, 'D');

    $library->moveFolderTo($b, $d);
    $library->rebuildPaths($owner);

    // After rebuildPaths, every folder is still anchored at the right place.
    expect($a->fresh()?->path)->toBe('/a');
    expect($d->fresh()?->path)->toBe('/d');
    expect($b->fresh()?->path)->toBe('/d/b');
    expect($c->fresh()?->path)->toBe('/d/b/c');

    expect(MediaLibraryFolder::query()->where('id', $b->id)->value('depth'))->toBe(1);
    expect(MediaLibraryFolder::query()->where('id', $c->id)->value('depth'))->toBe(2);
});
