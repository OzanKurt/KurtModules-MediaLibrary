<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;
use Kurt\Modules\MediaLibrary\Storage\Support\FocalPointCropper;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;
use Kurt\Modules\MediaLibrary\Storage\Support\VariantGenerator;

function makeVariantOwner(int $id = 31): Authenticatable&MediaLibraryOwner
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

function uploadFixtureItem(string $filename = 'src.png'): MediaLibraryItem
{
    /** @var BlurhashGenerator $blurhash */
    $blurhash = app(InterventionBlurhashGenerator::class);
    /** @var PaletteExtractor $palette */
    $palette = app(InterventionPaletteExtractor::class);

    $coordinator = new UploadCoordinator(
        new DefaultSubjectResolver,
        new MetadataExtractor($blurhash, $palette),
    );

    $file = new UploadedFile(
        __DIR__.'/../../fixtures/test.png',
        $filename,
        'image/png',
        null,
        true,
    );

    return $coordinator->upload($file, makeVariantOwner());
}

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
});

it('generates a new variant row + writes a file on disk', function (): void {
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Neither GD nor Imagick available.');
    }

    $item = uploadFixtureItem('source-a.png');
    $generator = new VariantGenerator(new FocalPointCropper);

    $variant = $generator->generateOrFetch($item, ['width' => 32, 'height' => 32, 'fit' => 'fit', 'format' => 'jpg']);

    expect($variant)->toBeInstanceOf(MediaLibraryVariant::class);
    expect($variant->item_id)->toBe($item->id);
    expect($variant->path)->toContain('media-library/variants/');
    expect(Storage::disk('public')->exists($variant->path))->toBeTrue();
});

it('returns the cached variant row + updates last_used_at on a second call', function (): void {
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Neither GD nor Imagick available.');
    }

    $item = uploadFixtureItem('source-b.png');
    $generator = new VariantGenerator(new FocalPointCropper);

    $spec = ['width' => 32, 'height' => 32, 'fit' => 'fit', 'format' => 'jpg'];

    $first = $generator->generateOrFetch($item, $spec);
    $firstUsedAt = $first->last_used_at;

    // Move clock forward then re-fetch.
    Carbon::setTestNow(now()->addMinute());

    $second = $generator->generateOrFetch($item, $spec);

    expect($second->id)->toBe($first->id);
    expect($second->last_used_at?->greaterThan($firstUsedAt ?? now()->subYear()))->toBeTrue();

    expect(MediaLibraryVariant::query()->where('item_id', $item->id)->count())->toBe(1);

    Carbon::setTestNow(null);
});

it('produces a different variant row for a different spec', function (): void {
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Neither GD nor Imagick available.');
    }

    $item = uploadFixtureItem('source-c.png');
    $generator = new VariantGenerator(new FocalPointCropper);

    $a = $generator->generateOrFetch($item, ['width' => 32, 'height' => 32, 'fit' => 'fit', 'format' => 'jpg']);
    $b = $generator->generateOrFetch($item, ['width' => 48, 'height' => 48, 'fit' => 'fit', 'format' => 'jpg']);

    expect($a->id)->not->toBe($b->id);
    expect($a->key)->not->toBe($b->key);
});

it('canonicalizes the key deterministically when spec keys are out of order', function (): void {
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Neither GD nor Imagick available.');
    }

    $item = uploadFixtureItem('source-d.png');
    $generator = new VariantGenerator(new FocalPointCropper);

    // Different array key order, same logical spec
    $a = $generator->generateOrFetch($item, ['width' => 32, 'height' => 32, 'fit' => 'fit', 'format' => 'jpg', 'quality' => 85]);
    $b = $generator->generateOrFetch($item, ['quality' => 85, 'format' => 'jpg', 'fit' => 'fit', 'height' => 32, 'width' => 32]);

    expect($b->id)->toBe($a->id);
    expect($a->key)->toBe($b->key);
});

it('applies focal-point crop when fit is crop', function (): void {
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Neither GD nor Imagick available.');
    }

    $item = uploadFixtureItem('source-e.png');
    $item->forceFill(['focal_x' => 0.25, 'focal_y' => 0.75])->save();

    $generator = new VariantGenerator(new FocalPointCropper);
    $variant = $generator->generateOrFetch($item, ['width' => 16, 'height' => 16, 'fit' => 'crop', 'format' => 'jpg']);

    expect($variant)->toBeInstanceOf(MediaLibraryVariant::class);
    expect($variant->spec['fit'])->toBe('crop');
    expect(Storage::disk('public')->exists($variant->path))->toBeTrue();
});
