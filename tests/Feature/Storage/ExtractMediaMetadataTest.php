<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Jobs\ExtractMediaMetadata;
use Kurt\Modules\MediaLibrary\Storage\Contracts\AiTagger;
use Kurt\Modules\MediaLibrary\Storage\Contracts\OcrExtractor;
use Kurt\Modules\MediaLibrary\Storage\Events\AiTagsAssigned;
use Kurt\Modules\MediaLibrary\Storage\Events\ExifExtracted;
use Kurt\Modules\MediaLibrary\Storage\Events\TextExtracted;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataPipeline;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.uploads.disk', 'public');
});

function uploadExifFixture(): MediaLibraryItem
{
    $owner = StubUser::create(['email' => 'exif@test.dev']);

    $file = new UploadedFile(
        __DIR__.'/../../fixtures/exif-gps.jpg',
        'photo.jpg',
        'image/jpeg',
        null,
        true,
    );

    return app(MediaLibrary::class)->upload($file, $owner);
}

it('dispatches ExtractMediaMetadata after a successful upload (queued mode)', function (): void {
    Queue::fake();

    $item = uploadExifFixture();

    Queue::assertPushed(
        ExtractMediaMetadata::class,
        fn (ExtractMediaMetadata $job): bool => $job->itemId === $item->id,
    );
});

it('runs the pipeline inline in sync dispatch mode', function (): void {
    if (! extension_loaded('exif')) {
        $this->markTestSkipped('ext-exif not available.');
    }

    config()->set('media-library.extractors.dispatch', 'sync');

    // No Queue::fake: sync mode must run the pipeline inline during the request,
    // so EXIF is already persisted by the time upload() returns.
    $item = uploadExifFixture();

    expect($item->refresh()->exif)->toBeArray()->toHaveKey('GPSLatitude');
});

it('extracts EXIF (incl. GPS) and dimensions when the pipeline runs', function (): void {
    if (! extension_loaded('exif')) {
        $this->markTestSkipped('ext-exif not available.');
    }

    Queue::fake(); // keep the auto-dispatched job from running twice
    Event::fake([ExifExtracted::class]);

    $item = uploadExifFixture();

    app(MetadataPipeline::class)->run($item->refresh());
    $item->refresh();

    expect($item->width)->toBe(48)
        ->and($item->height)->toBe(32)
        ->and($item->exif)->toBeArray()
        ->and($item->exif)->toHaveKey('GPSLatitude')
        ->and($item->exif['GPSLatitudeRef'])->toBe('N');

    Event::assertDispatched(ExifExtracted::class);
});

it('runs a configured stub OCR extractor and persists its output', function (): void {
    Queue::fake();
    Event::fake([TextExtracted::class]);

    // Bind a stub engine into the container, mirroring a consumer wiring
    // `media-library.contracts.ocr` to their own implementation.
    app()->instance(OcrExtractor::class, new class implements OcrExtractor
    {
        public function extract(string $path): string
        {
            return 'stub ocr output';
        }
    });

    $item = uploadExifFixture();

    app(MetadataPipeline::class)->run($item->refresh());

    expect($item->refresh()->extracted_text)->toBe('stub ocr output');
    Event::assertDispatched(TextExtracted::class);
});

it('runs a configured stub AI tagger and persists its tags', function (): void {
    Queue::fake();
    Event::fake([AiTagsAssigned::class]);

    app()->instance(AiTagger::class, new class implements AiTagger
    {
        /** @return array<int, string> */
        public function tag(string $path): array
        {
            return ['sunset', 'beach'];
        }
    });

    $item = uploadExifFixture();

    app(MetadataPipeline::class)->run($item->refresh());

    expect($item->refresh()->ai_tags)->toBe(['sunset', 'beach']);
    Event::assertDispatched(AiTagsAssigned::class);
});

it('skips unbound OCR / AI steps gracefully (no writes, no error)', function (): void {
    Queue::fake();

    $item = uploadExifFixture();

    // ocr / ai_tagger are unbound by default — the pipeline must not touch them.
    app(MetadataPipeline::class)->run($item->refresh());

    expect($item->refresh()->extracted_text)->toBeNull()
        ->and($item->ai_tags)->toBeNull();
});
