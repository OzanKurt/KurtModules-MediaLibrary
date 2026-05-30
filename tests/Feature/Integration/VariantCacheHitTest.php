<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;
use Kurt\Modules\MediaLibrary\Storage\Support\VariantGenerator;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Neither GD nor Imagick available.');
    }
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
});

it('returns the same variant row on a second call and does not write a new file', function (): void {
    $owner = StubUser::create(['email' => 'variantcache@test.dev']);
    $library = app(MediaLibrary::class);

    $item = $library->upload(
        new UploadedFile(__DIR__.'/../../fixtures/test.png', 'cache.png', 'image/png', null, true),
        $owner,
    );

    $generator = app(VariantGenerator::class);
    $spec = ['width' => 64, 'height' => 64, 'fit' => 'fit', 'format' => 'jpg'];

    $first = $generator->generateOrFetch($item, $spec);
    $firstUsedAt = $first->last_used_at;
    $variantPath = $first->path;

    expect(Storage::disk('public')->exists($variantPath))->toBeTrue();

    // Bump the clock so we can observe a fresh last_used_at on cache hit.
    Carbon::setTestNow(now()->addMinute());

    $second = $generator->generateOrFetch($item, $spec);

    expect($second->id)->toBe($first->id);
    expect(MediaLibraryVariant::query()->where('item_id', $item->id)->count())->toBe(1);
    expect($second->last_used_at?->greaterThan($firstUsedAt ?? now()->subYear()))->toBeTrue();

    Carbon::setTestNow(null);
});
