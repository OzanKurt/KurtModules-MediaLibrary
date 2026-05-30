<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Catalog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

/**
 * @extends Factory<MediaLibraryAttachment>
 */
class MediaLibraryAttachmentFactory extends Factory
{
    /** @var class-string<MediaLibraryAttachment> */
    protected $model = MediaLibraryAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => MediaLibraryItem::factory(),
            'attachable_type' => 'stub_attachable',
            'attachable_id' => $this->faker->numberBetween(1, 1000),
            'role' => 'attachment',
            'position' => 0,
        ];
    }
}
