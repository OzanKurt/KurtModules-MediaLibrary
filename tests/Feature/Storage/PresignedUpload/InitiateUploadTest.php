<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
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

function buildPresignedCoordinator(): UploadCoordinator
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

function makePresignedStubOwner(int $id = 11): Authenticatable&MediaLibraryOwner
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
    config()->set('media-library.uploads.presigned_ttl_seconds', 900);
});

it('returns a pending upload row with status Pending and a future expires_at', function (): void {
    $coordinator = buildPresignedCoordinator();

    $pending = $coordinator->initiateUpload(makePresignedStubOwner(), [
        'filename' => 'beach.png',
        'mime_type' => 'image/png',
        'byte_size' => 4096,
    ]);

    expect($pending)->toBeInstanceOf(MediaLibraryPendingUpload::class);
    expect($pending->status)->toBe(PendingUploadStatus::Pending);
    expect($pending->upload_id)->not->toBe('');
    expect($pending->expires_at?->isFuture())->toBeTrue();
});

it('persists driver_payload with url, headers, key, and disk', function (): void {
    $coordinator = buildPresignedCoordinator();

    $pending = $coordinator->initiateUpload(makePresignedStubOwner(), [
        'filename' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    $payload = $pending->driver_payload;

    expect($payload)->toHaveKeys(['url', 'headers', 'key', 'disk']);
    expect($payload['disk'])->toBe('s3');
    expect($payload['key'])->toContain($pending->upload_id);
    expect($payload['key'])->toEndWith('photo.jpg');
    expect($payload['url'])->toBeString()->not->toBe('');
    expect($payload['headers'])->toBeArray();
});

it('throws InvalidUpload when filename or mime_type are missing', function (): void {
    $coordinator = buildPresignedCoordinator();
    $owner = makePresignedStubOwner();

    expect(fn () => $coordinator->initiateUpload($owner, ['filename' => 'a.png']))
        ->toThrow(InvalidUpload::class);

    expect(fn () => $coordinator->initiateUpload($owner, ['mime_type' => 'image/png']))
        ->toThrow(InvalidUpload::class);
});

it('throws InvalidUpload when mime_type is not allowed by config', function (): void {
    config()->set('media-library.uploads.allowed_mimes', ['image/png']);

    $coordinator = buildPresignedCoordinator();

    expect(fn () => $coordinator->initiateUpload(makePresignedStubOwner(), [
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
    ]))->toThrow(InvalidUpload::class);
});

it('throws InvalidUpload when byte_size exceeds max_size_kb', function (): void {
    config()->set('media-library.uploads.max_size_kb', 1);

    $coordinator = buildPresignedCoordinator();

    expect(fn () => $coordinator->initiateUpload(makePresignedStubOwner(), [
        'filename' => 'big.png',
        'mime_type' => 'image/png',
        'byte_size' => 1024 * 1024,
    ]))->toThrow(InvalidUpload::class);
});

it('honors the configured presigned_ttl_seconds when computing expires_at', function (): void {
    config()->set('media-library.uploads.presigned_ttl_seconds', 120);

    $coordinator = buildPresignedCoordinator();

    $pending = $coordinator->initiateUpload(makePresignedStubOwner(), [
        'filename' => 'short.png',
        'mime_type' => 'image/png',
    ]);

    $diffFromNow = (int) abs($pending->expires_at->diffInSeconds(now()));

    // TTL was 120s; allow a generous jitter range to keep this test stable.
    expect($diffFromNow)->toBeGreaterThan(110)->toBeLessThan(130);
    expect($pending->expires_at->isFuture())->toBeTrue();
});
