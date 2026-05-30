<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemReplaced;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\ReplaceFailed;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\ReplaceCoordinator;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;

function makeReplaceOwner(int $id = 41): Authenticatable&MediaLibraryOwner
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
            return 'Stub Owner '.$this->id;
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

function makeMetadataExtractor(): MetadataExtractor
{
    /** @var BlurhashGenerator $blurhash */
    $blurhash = app(InterventionBlurhashGenerator::class);
    /** @var PaletteExtractor $palette */
    $palette = app(InterventionPaletteExtractor::class);

    return new MetadataExtractor($blurhash, $palette);
}

function uploadReplaceableItem(string $filename = 'orig.png'): MediaLibraryItem
{
    $coordinator = new UploadCoordinator(new DefaultSubjectResolver, makeMetadataExtractor());

    $file = new UploadedFile(
        __DIR__.'/../../fixtures/test.png',
        $filename,
        'image/png',
        null,
        true,
    );

    return $coordinator->upload($file, makeReplaceOwner());
}

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
});

it('creates a version row pointing at the previous spatie media id and keeps the item id stable', function (): void {
    Event::fake([ItemReplaced::class]);

    $item = uploadReplaceableItem('first.png');
    $originalId = $item->id;
    $previousMediaId = (int) ($item->storage?->getFirstMedia('mli')?->id ?? 0);

    expect($previousMediaId)->toBeGreaterThan(0);

    $replacement = new UploadedFile(
        __DIR__.'/../../fixtures/test.jpg',
        'second.jpg',
        'image/jpeg',
        null,
        true,
    );

    $replacer = new ReplaceCoordinator(makeMetadataExtractor());
    $replaced = $replacer->replace($item, $replacement, 'updated photo');

    expect($replaced->id)->toBe($originalId);
    expect($replaced->filename)->toBe('second.jpg');
    expect($replaced->mime_type)->toBe('image/jpeg');

    $version = MediaLibraryVersion::query()->where('item_id', $originalId)->first();
    expect($version)->not->toBeNull();
    expect($version?->spatie_media_id)->toBe($previousMediaId);
    expect($version?->changelog)->toBe('updated photo');

    Event::assertDispatched(ItemReplaced::class, function (ItemReplaced $event) use ($originalId, $previousMediaId): bool {
        return $event->item->id === $originalId
            && $event->previousSpatieMediaId === $previousMediaId;
    });
});

it('preserves polymorphic attachment rows because the item id stays the same', function (): void {
    Event::fake();

    $item = uploadReplaceableItem('attached.png');
    $attachment = MediaLibraryAttachment::factory()->create([
        'item_id' => $item->id,
        'attachable_type' => 'demo_post',
        'attachable_id' => 99,
        'role' => 'hero',
    ]);

    $replacement = new UploadedFile(
        __DIR__.'/../../fixtures/test.jpg',
        'attached-v2.jpg',
        'image/jpeg',
        null,
        true,
    );

    $replacer = new ReplaceCoordinator(makeMetadataExtractor());
    $replaced = $replacer->replace($item, $replacement, 'v2');

    $fresh = MediaLibraryAttachment::query()->find($attachment->id);
    expect($fresh)->not->toBeNull();
    expect($fresh?->item_id)->toBe($replaced->id);
});

it('invalidates ad-hoc variant rows after a replace', function (): void {
    Event::fake();

    $item = uploadReplaceableItem('variants.png');
    MediaLibraryVariant::factory()->count(3)->create(['item_id' => $item->id]);

    expect(MediaLibraryVariant::query()->where('item_id', $item->id)->count())->toBe(3);

    $replacement = new UploadedFile(
        __DIR__.'/../../fixtures/test.jpg',
        'variants-v2.jpg',
        'image/jpeg',
        null,
        true,
    );

    $replacer = new ReplaceCoordinator(makeMetadataExtractor());
    $replacer->replace($item, $replacement, 'cleared variants');

    expect(MediaLibraryVariant::query()->where('item_id', $item->id)->count())->toBe(0);
});

it('throws ReplaceFailed when the item has no storage host', function (): void {
    Event::fake();

    $item = MediaLibraryItem::factory()->create();

    // Force-detach the storage so the replace path observes a missing host.
    $item->forceFill(['storage_id' => 0])->save();
    $item->unsetRelation('storage');

    $replacement = new UploadedFile(
        __DIR__.'/../../fixtures/test.jpg',
        'orphan.jpg',
        'image/jpeg',
        null,
        true,
    );

    $replacer = new ReplaceCoordinator(makeMetadataExtractor());

    expect(fn () => $replacer->replace($item, $replacement, 'orphan'))
        ->toThrow(ReplaceFailed::class);
});

it('throws ReplaceFailed when the storage host has no media to replace', function (): void {
    Event::fake();

    $item = MediaLibraryItem::factory()->create();
    // Factory creates a storage row but does not attach any media file.

    $replacement = new UploadedFile(
        __DIR__.'/../../fixtures/test.jpg',
        'empty-host.jpg',
        'image/jpeg',
        null,
        true,
    );

    $replacer = new ReplaceCoordinator(makeMetadataExtractor());

    expect(fn () => $replacer->replace($item, $replacement, 'empty'))
        ->toThrow(ReplaceFailed::class);
});
