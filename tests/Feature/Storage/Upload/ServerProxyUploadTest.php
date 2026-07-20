<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemUploaded;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryStorage;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\InvalidUpload;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

/**
 * Build an UploadCoordinator with the real DefaultSubjectResolver
 * and Intervention-backed extractors. Manual instantiation mirrors what
 * the Task 23 service provider will eventually do.
 */
function buildCoordinator(): UploadCoordinator
{
    /** @var BlurhashGenerator $blurhash */
    $blurhash = app(InterventionBlurhashGenerator::class);
    /** @var PaletteExtractor $palette */
    $palette = app(InterventionPaletteExtractor::class);

    return new UploadCoordinator(
        new DefaultSubjectResolver,
        new MetadataExtractor($blurhash, $palette),
    );
}

function makeStubOwner(int $id = 7): Authenticatable&MediaLibraryOwner
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

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
});

it('uploads a PNG and persists the MediaLibraryItem with attached spatie media', function (): void {
    Event::fake([ItemUploaded::class]);

    $owner = makeStubOwner();
    $coordinator = buildCoordinator();

    $file = new UploadedFile(
        __DIR__.'/../../../fixtures/test.png',
        'sample-photo.png',
        'image/png',
        null,
        true,
    );

    $item = $coordinator->upload($file, $owner);

    expect($item)->toBeInstanceOf(MediaLibraryItem::class);
    expect($item->owner_type)->toBe('stub_owner');
    expect((int) $item->owner_id)->toBe(7);
    expect($item->filename)->toBe('sample-photo.png');
    expect($item->mime_type)->toBe('image/png');
    expect($item->byte_size)->toBeGreaterThan(0);

    expect($item->storage_id)->not->toBeNull();
    $storage = MediaLibraryStorage::query()->find($item->storage_id);
    expect($storage)->not->toBeNull();
    expect($storage?->getFirstMedia('mli'))->not->toBeNull();

    Event::assertDispatched(ItemUploaded::class, fn (ItemUploaded $event): bool => $event->item->id === $item->id);
});

it('extracts dimensions, blurhash, and palette synchronously for an image', function (): void {
    Event::fake();

    $coordinator = buildCoordinator();

    $file = new UploadedFile(
        __DIR__.'/../../../fixtures/test.png',
        'square.png',
        'image/png',
        null,
        true,
    );

    $item = $coordinator->upload($file, makeStubOwner());

    expect($item->width)->toBe(64);
    expect($item->height)->toBe(64);

    if (extension_loaded('gd') || extension_loaded('imagick')) {
        expect($item->blurhash)->not->toBeNull()->not->toBe('');
        expect($item->dominant_color)->not->toBeNull();
    }
});

it('increments folder item_count when a folder is provided', function (): void {
    Event::fake();

    $coordinator = buildCoordinator();
    $folder = MediaLibraryFolder::factory()->create(['item_count' => 0]);

    $file = new UploadedFile(
        __DIR__.'/../../../fixtures/test.png',
        'in-folder.png',
        'image/png',
        null,
        true,
    );

    $item = $coordinator->upload($file, makeStubOwner(), $folder);

    expect($item->folder_id)->toBe($folder->id);
    expect($folder->fresh()?->item_count)->toBe(1);
});

it('falls back to the auth user via the subject resolver when owner is null', function (): void {
    Event::fake();

    $owner = makeStubOwner(99);
    $this->be($owner);

    $coordinator = buildCoordinator();

    $file = new UploadedFile(
        __DIR__.'/../../../fixtures/test.png',
        'auth-user.png',
        'image/png',
        null,
        true,
    );

    $item = $coordinator->upload($file, null);

    expect($item->owner_type)->toBe('stub_owner');
    expect((int) $item->owner_id)->toBe(99);
});

it('rejects an upload whose real mime type is not allowed', function (): void {
    Event::fake();
    config()->set('media-library.uploads.allowed_mimes', ['application/pdf']);

    // Real content is a PNG; the client-declared mime is irrelevant because
    // upload() validates the content-derived mime.
    $file = new UploadedFile(
        __DIR__.'/../../../fixtures/test.png',
        'not-a-pdf.png',
        'image/png',
        null,
        true,
    );

    expect(fn () => buildCoordinator()->upload($file, makeStubOwner()))
        ->toThrow(InvalidUpload::class);
});

it('rejects an oversize upload based on the real file size', function (): void {
    Event::fake();
    // Disable the mime allow-list so this test isolates the size check.
    config()->set('media-library.uploads.allowed_mimes', []);
    config()->set('media-library.uploads.max_size_kb', 100);

    $file = UploadedFile::fake()->create('large.bin', 500); // 500 KB > 100 KB limit

    expect(fn () => buildCoordinator()->upload($file, makeStubOwner()))
        ->toThrow(InvalidUpload::class);
});

it('sanitizes a path-traversal filename before storing', function (): void {
    Event::fake();

    $file = new UploadedFile(
        __DIR__.'/../../../fixtures/test.png',
        '../../etc/passwd.png',
        'image/png',
        null,
        true,
    );

    $item = buildCoordinator()->upload($file, makeStubOwner());

    expect($item->filename)->not->toContain('..');
    expect($item->filename)->not->toContain('/');
    expect($item->filename)->toBe('passwd.png');

    $media = $item->spatieMedia();
    expect($media?->file_name)->not->toContain('..');
    expect($media?->file_name)->not->toContain('/');
});

it('records auth user id as created_by and updated_by when authenticated', function (): void {
    Event::fake();

    $user = StubUser::create(['id' => 5, 'email' => 'uploader@test.dev']);
    $this->be($user);

    $coordinator = buildCoordinator();

    $file = new UploadedFile(
        __DIR__.'/../../../fixtures/test.png',
        'attribution.png',
        'image/png',
        null,
        true,
    );

    $owner = makeStubOwner();
    $item = $coordinator->upload($file, $owner);

    expect($item->created_by)->toBe(5);
    expect($item->updated_by)->toBe(5);
});
