<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderCreated;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderMoved;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemRestored;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemTagged;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemTrashed;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemUntagged;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
use Kurt\Modules\MediaLibrary\Catalog\Observers\MediaLibraryFolderObserver;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\SelfReferentialFolder;
use Kurt\Modules\MediaLibrary\Sharing\Events\ShareLinkCreated;
use Kurt\Modules\MediaLibrary\Sharing\Events\ShareLinkRevoked;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkSigner;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\ReplaceCoordinator;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

/**
 * Wire a MediaLibrary facade-service with concrete dependencies.
 * Service-provider wiring is Task 23 — for now we instantiate manually.
 */
function buildMediaLibrary(): MediaLibrary
{
    /** @var BlurhashGenerator $blurhash */
    $blurhash = app(InterventionBlurhashGenerator::class);
    /** @var PaletteExtractor $palette */
    $palette = app(InterventionPaletteExtractor::class);
    $extractor = new MetadataExtractor($blurhash, $palette);

    return new MediaLibrary(
        uploads: new UploadCoordinator(new DefaultSubjectResolver, $extractor),
        replaces: new ReplaceCoordinator($extractor),
        signer: new ShareLinkSigner,
    );
}

function makeFacadeOwner(int $id = 88): Authenticatable&MediaLibraryOwner
{
    return new class($id) implements Authenticatable, MediaLibraryOwner
    {
        public function __construct(private readonly int $id) {}

        public function getKey(): int|string
        {
            return $this->id;
        }

        public function getMorphClass(): string
        {
            return 'stub_owner';
        }

        public function getMediaLibraryDisplayName(): string
        {
            return 'Owner '.$this->id;
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };
}

beforeEach(function (): void {
    MediaLibraryFolder::observe(MediaLibraryFolderObserver::class);
});

afterEach(function (): void {
    MediaLibraryFolder::flushEventListeners();
});

it('creates a folder and dispatches FolderCreated', function (): void {
    Event::fake([FolderCreated::class]);
    $svc = buildMediaLibrary();

    $folder = $svc->createFolder(makeFacadeOwner(11), 'Marketing');

    expect($folder->exists)->toBeTrue();
    expect($folder->owner_id)->toBe(11);
    expect($folder->slug)->toBe('marketing');

    Event::assertDispatched(FolderCreated::class, fn (FolderCreated $e): bool => $e->folder->id === $folder->id);
});

it('createFolder nests under a parent folder', function (): void {
    Event::fake([FolderCreated::class]);
    $svc = buildMediaLibrary();

    $owner = makeFacadeOwner(12);
    $parent = $svc->createFolder($owner, 'Parent');
    $child = $svc->createFolder($owner, 'Child', $parent);

    expect($child->parent_id)->toBe($parent->id);
});

it('moveFolderTo updates path + depth and dispatches FolderMoved', function (): void {
    Event::fake([FolderMoved::class]);
    $svc = buildMediaLibrary();

    $owner = makeFacadeOwner(13);
    $rootA = $svc->createFolder($owner, 'A');
    $rootB = $svc->createFolder($owner, 'B');
    $child = $svc->createFolder($owner, 'Child', $rootA);

    $moved = $svc->moveFolderTo($child, $rootB);

    expect($moved->parent_id)->toBe($rootB->id);
    expect($moved->path)->toBe($rootB->path.'/child');
    expect($moved->depth)->toBe($rootB->depth + 1);

    Event::assertDispatched(FolderMoved::class, function (FolderMoved $e) use ($child, $rootA, $rootB): bool {
        return $e->folder->id === $child->id
            && $e->oldParentId === $rootA->id
            && $e->newParentId === $rootB->id;
    });
});

it('moveFolderTo throws SelfReferentialFolder when target is the folder itself', function (): void {
    Event::fake([FolderMoved::class]);
    $svc = buildMediaLibrary();

    $owner = makeFacadeOwner(14);
    $folder = $svc->createFolder($owner, 'Self');

    $svc->moveFolderTo($folder, $folder);
})->throws(SelfReferentialFolder::class);

it('moveItems updates folder_id on each matching item', function (): void {
    Event::fake([FolderMoved::class]);
    $svc = buildMediaLibrary();

    $owner = makeFacadeOwner(15);
    $folder = $svc->createFolder($owner, 'Target');

    $items = MediaLibraryItem::factory()->count(3)->create(['folder_id' => null]);
    $ids = $items->pluck('id')->all();

    $affected = $svc->moveItems($ids, $folder);

    expect($affected)->toBe(3);
    foreach ($ids as $id) {
        expect(MediaLibraryItem::query()->find($id)?->folder_id)->toBe($folder->id);
    }
});

it('moveItems accepts a null folder to move items out of folders', function (): void {
    Event::fake([FolderMoved::class]);
    $svc = buildMediaLibrary();

    $owner = makeFacadeOwner(16);
    $folder = $svc->createFolder($owner, 'Origin');
    $items = MediaLibraryItem::factory()->count(2)->create(['folder_id' => $folder->id]);

    $svc->moveItems($items->pluck('id')->all(), null);

    foreach ($items as $item) {
        expect($item->fresh()?->folder_id)->toBeNull();
    }
});

it('moveItems keeps source and target folder item_count correct in one transaction', function (): void {
    Event::fake([FolderMoved::class]);
    $svc = buildMediaLibrary();

    $owner = makeFacadeOwner(21);
    $source = $svc->createFolder($owner, 'Source');
    $target = $svc->createFolder($owner, 'Target');

    $items = MediaLibraryItem::factory()->count(2)->create(['folder_id' => $source->id]);
    // Seed the source counter to match its real contents.
    $source->forceFill(['item_count' => 2])->save();

    $moved = $svc->moveItems($items->pluck('id')->all(), $target);

    expect($moved)->toBe(2);
    expect($source->fresh()?->item_count)->toBe(0);
    expect($target->fresh()?->item_count)->toBe(2);
});

it('moveItems scopes the move to the given owner', function (): void {
    Event::fake([FolderMoved::class]);
    $svc = buildMediaLibrary();

    $owner = makeFacadeOwner(22);
    $target = $svc->createFolder($owner, 'Owned Target');

    $mine = MediaLibraryItem::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 22, 'folder_id' => null]);
    $theirs = MediaLibraryItem::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 999, 'folder_id' => null]);

    $moved = $svc->moveItems([$mine->id, $theirs->id], $target, $owner);

    expect($moved)->toBe(1);
    expect($mine->fresh()?->folder_id)->toBe($target->id);
    expect($theirs->fresh()?->folder_id)->toBeNull();
    expect($target->fresh()?->item_count)->toBe(1);
});

it('trash + restore round-trips an item and dispatches the right events', function (): void {
    Event::fake([ItemTrashed::class, ItemRestored::class]);
    $svc = buildMediaLibrary();

    $item = MediaLibraryItem::factory()->create();
    $svc->trash($item);

    expect(MediaLibraryItem::query()->find($item->id))->toBeNull();
    expect(MediaLibraryItem::query()->withTrashed()->find($item->id))->not->toBeNull();
    Event::assertDispatched(ItemTrashed::class, fn (ItemTrashed $e): bool => $e->item->id === $item->id);

    $trashed = MediaLibraryItem::query()->withTrashed()->find($item->id);
    expect($trashed)->not->toBeNull();
    $svc->restore($trashed);

    expect(MediaLibraryItem::query()->find($item->id))->not->toBeNull();
    Event::assertDispatched(ItemRestored::class, fn (ItemRestored $e): bool => $e->item->id === $item->id);
});

it('shareItem persists a share link, dispatches ShareLinkCreated, and returns a URL', function (): void {
    Event::fake([ShareLinkCreated::class]);
    config()->set('media-library.routes.share_prefix', 'media-library/share');
    $svc = buildMediaLibrary();

    $item = MediaLibraryItem::factory()->create();
    $url = $svc->shareItem($item, expiresInSeconds: 3600, abilities: ['view', 'download']);

    expect($url)->toContain('/media-library/share/');

    $link = ShareLink::query()->where('item_id', $item->id)->first();
    expect($link)->not->toBeNull();
    expect($link?->abilities)->toBe(['view', 'download']);
    expect($link?->expires_at)->not->toBeNull();

    Event::assertDispatched(ShareLinkCreated::class, fn (ShareLinkCreated $e): bool => $e->link->id === $link?->id);
});

it('shareFolder persists a folder share and uses a null expiry when expiresInSeconds is 0', function (): void {
    Event::fake([ShareLinkCreated::class]);
    $svc = buildMediaLibrary();

    $owner = makeFacadeOwner(17);
    $folder = $svc->createFolder($owner, 'Shared');
    $url = $svc->shareFolder($folder, expiresInSeconds: 0);

    expect($url)->toBeString();
    $link = ShareLink::query()->where('folder_id', $folder->id)->first();
    expect($link)->not->toBeNull();
    expect($link?->expires_at)->toBeNull();
});

it('revokeShare sets revoked_at and dispatches ShareLinkRevoked', function (): void {
    Event::fake([ShareLinkRevoked::class]);
    $svc = buildMediaLibrary();

    $link = ShareLink::factory()->create(['token' => 'revoke-me']);
    $svc->revokeShare('revoke-me');

    expect($link->fresh()?->revoked_at)->not->toBeNull();
    Event::assertDispatched(ShareLinkRevoked::class);
});

it('revokeShare is a no-op for an unknown token', function (): void {
    Event::fake([ShareLinkRevoked::class]);
    $svc = buildMediaLibrary();

    $svc->revokeShare('does-not-exist');

    Event::assertNotDispatched(ShareLinkRevoked::class);
});

it('tag firstOrCreates a scoped tag and dispatches ItemTagged', function (): void {
    Event::fake([ItemTagged::class]);
    $svc = buildMediaLibrary();

    $item = MediaLibraryItem::factory()->create([
        'owner_type' => 'stub_owner',
        'owner_id' => 21,
    ]);

    $tag = $svc->tag($item, 'Campaign 2026');

    expect($tag->exists)->toBeTrue();
    expect($tag->slug)->toBe('campaign-2026');
    expect($item->tags()->where('media_library_tags.id', $tag->id)->exists())->toBeTrue();

    Event::assertDispatched(ItemTagged::class);
});

it('tag reuses an existing tag for the owner', function (): void {
    Event::fake([ItemTagged::class]);
    $svc = buildMediaLibrary();

    $item = MediaLibraryItem::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 22]);
    $existing = MediaLibraryTag::create([
        'owner_type' => 'stub_owner',
        'owner_id' => 22,
        'slug' => 'reused',
        'name' => ['en' => 'Reused'],
    ]);

    $tag = $svc->tag($item, 'Reused');

    expect($tag->id)->toBe($existing->id);
});

it('untag detaches the tag and dispatches ItemUntagged', function (): void {
    Event::fake([ItemUntagged::class]);
    $svc = buildMediaLibrary();

    $item = MediaLibraryItem::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 23]);
    $tag = MediaLibraryTag::create([
        'owner_type' => 'stub_owner',
        'owner_id' => 23,
        'slug' => 'toremove',
        'name' => ['en' => 'Toremove'],
    ]);
    $item->tags()->attach($tag->id);

    $svc->untag($item, 'Toremove');

    expect($item->tags()->where('media_library_tags.id', $tag->id)->exists())->toBeFalse();
    Event::assertDispatched(ItemUntagged::class);
});

it('untag is a no-op when the named tag does not exist for the owner', function (): void {
    Event::fake([ItemUntagged::class]);
    $svc = buildMediaLibrary();

    $item = MediaLibraryItem::factory()->create(['owner_type' => 'stub_owner', 'owner_id' => 24]);

    $svc->untag($item, 'nope');

    Event::assertNotDispatched(ItemUntagged::class);
});

it('saveSearch persists the filters and runSearch returns matching items', function (): void {
    $svc = buildMediaLibrary();

    $user = new StubUser;
    $user->forceFill(['id' => 5]);

    $image = MediaLibraryItem::factory()->create(['mime_type' => 'image/jpeg']);
    MediaLibraryItem::factory()->create(['mime_type' => 'application/pdf']);

    $search = $svc->saveSearch($user, 'images only', ['mime' => 'image/*']);
    expect($search)->toBeInstanceOf(MediaLibrarySavedSearch::class);

    $results = $svc->runSearch($search);

    expect($results)->toHaveCount(1);
    expect($results->first()?->id)->toBe($image->id);
});

it('pruneVersions deletes the oldest versions beyond keepNewest and returns the count', function (): void {
    $svc = buildMediaLibrary();

    $item = MediaLibraryItem::factory()->create();
    for ($i = 0; $i < 5; $i++) {
        MediaLibraryVersion::factory()->create([
            'item_id' => $item->id,
            'created_at' => now()->subMinutes(5 - $i),
            'updated_at' => now()->subMinutes(5 - $i),
        ]);
    }

    $pruned = $svc->pruneVersions($item, keepNewest: 2);

    expect($pruned)->toBe(3);
    expect(MediaLibraryVersion::query()->where('item_id', $item->id)->count())->toBe(2);
});
