<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Storage;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;

/**
 * @extends Factory<MediaLibraryPendingUpload>
 */
class MediaLibraryPendingUploadFactory extends Factory
{
    /** @var class-string<MediaLibraryPendingUpload> */
    protected $model = MediaLibraryPendingUpload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => 'stub_owner',
            'owner_id' => $this->faker->numberBetween(1, 1000),
            'upload_id' => (string) Str::uuid(),
            'filename' => $this->faker->unique()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => $this->faker->numberBetween(1024, 1_000_000),
            'driver' => 's3',
            'driver_payload' => ['url' => 'https://example.test/upload', 'key' => 'uploads/'.$this->faker->uuid()],
            'status' => PendingUploadStatus::Pending,
            'expires_at' => now()->addHour(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PendingUploadStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => PendingUploadStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);
    }
}
