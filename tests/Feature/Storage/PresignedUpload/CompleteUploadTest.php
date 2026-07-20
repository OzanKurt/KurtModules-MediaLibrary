<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemUploaded;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\InvalidUpload;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;

function buildCompleteCoordinator(): UploadCoordinator
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

function makeCompleteStubOwner(int $id = 21): Authenticatable&MediaLibraryOwner
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
    Storage::fake('s3');
    config()->set('media-library.uploads.disk', 's3');
});

it('completes a pending upload after the file lands on the S3 disk', function (): void {
    Event::fake([ItemUploaded::class]);

    $coordinator = buildCompleteCoordinator();

    $pending = $coordinator->initiateUpload(makeCompleteStubOwner(), [
        'filename' => 'beach.png',
        'mime_type' => 'image/png',
    ]);

    // Simulate the direct PUT to the S3 disk
    $payload = $pending->driver_payload;
    $key = (string) $payload['key'];
    Storage::disk('s3')->put($key, (string) file_get_contents(__DIR__.'/../../../fixtures/test.png'));

    $item = $coordinator->completeUpload($pending->upload_id);

    expect($item)->toBeInstanceOf(MediaLibraryItem::class);
    expect($item->owner_type)->toBe('stub_owner');
    expect($item->filename)->toBe('beach.png');
    expect($item->mime_type)->toBe('image/png');

    $pending->refresh();
    expect($pending->status)->toBe(PendingUploadStatus::Completed);
    expect($pending->completed_at)->not->toBeNull();

    Event::assertDispatched(ItemUploaded::class, fn (ItemUploaded $event): bool => $event->item->id === $item->id);
});

it('extracts dimensions and palette on completion when the object is a real image', function (): void {
    Event::fake();
    $coordinator = buildCompleteCoordinator();

    $pending = $coordinator->initiateUpload(makeCompleteStubOwner(), [
        'filename' => 'square.png',
        'mime_type' => 'image/png',
    ]);

    Storage::disk('s3')->put((string) $pending->driver_payload['key'], (string) file_get_contents(__DIR__.'/../../../fixtures/test.png'));

    $item = $coordinator->completeUpload($pending->upload_id);

    expect($item->width)->toBe(64);
    expect($item->height)->toBe(64);
});

it('throws InvalidUpload when the pending row does not exist', function (): void {
    $coordinator = buildCompleteCoordinator();

    expect(fn () => $coordinator->completeUpload('missing-upload-id'))
        ->toThrow(InvalidUpload::class, 'pending upload not found');
});

it('throws InvalidUpload when the pending row is already completed', function (): void {
    $coordinator = buildCompleteCoordinator();

    $pending = MediaLibraryPendingUpload::factory()->completed()->create();

    expect(fn () => $coordinator->completeUpload($pending->upload_id))
        ->toThrow(InvalidUpload::class);
});

it('throws InvalidUpload when the pending row has expired', function (): void {
    $coordinator = buildCompleteCoordinator();

    $pending = MediaLibraryPendingUpload::factory()->expired()->create();

    expect(fn () => $coordinator->completeUpload($pending->upload_id))
        ->toThrow(InvalidUpload::class);
});

it('rejects a completed upload when the real object exceeds the size limit', function (): void {
    Event::fake();
    // Disable the mime allow-list so this test isolates the size re-check, and
    // set a 1 KB limit. The client declares no byte_size at initiate, so the
    // initiate-time size check is skipped entirely.
    config()->set('media-library.uploads.allowed_mimes', []);
    config()->set('media-library.uploads.max_size_kb', 1);

    $coordinator = buildCompleteCoordinator();

    $pending = $coordinator->initiateUpload(makeCompleteStubOwner(), [
        'filename' => 'declared-small.png',
        'mime_type' => 'image/png',
    ]);

    // The client PUTs an object far larger than the declared/allowed size to
    // the presigned URL. completeUpload must reject it against the real object.
    Storage::disk('s3')->put((string) $pending->driver_payload['key'], str_repeat('a', 5 * 1024));

    expect(fn () => $coordinator->completeUpload($pending->upload_id))
        ->toThrow(InvalidUpload::class);

    $pending->refresh();
    expect($pending->status)->toBe(PendingUploadStatus::Pending);
});

it('throws InvalidUpload when the uploaded object cannot be found on the disk', function (): void {
    $coordinator = buildCompleteCoordinator();

    $pending = $coordinator->initiateUpload(makeCompleteStubOwner(), [
        'filename' => 'missing.png',
        'mime_type' => 'image/png',
    ]);

    expect(fn () => $coordinator->completeUpload($pending->upload_id))
        ->toThrow(InvalidUpload::class, 'uploaded object not found on disk');
});
