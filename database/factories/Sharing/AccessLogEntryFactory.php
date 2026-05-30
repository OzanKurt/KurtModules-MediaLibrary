<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Sharing;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Models\AccessLogEntry;

/**
 * @extends Factory<AccessLogEntry>
 */
class AccessLogEntryFactory extends Factory
{
    /** @var class-string<AccessLogEntry> */
    protected $model = AccessLogEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => MediaLibraryItem::factory(),
            'share_link_id' => null,
            'user_id' => null,
            'action' => AccessAction::View,
            'ip' => $this->faker->ipv4(),
            'user_agent' => 'Mozilla/5.0 Stub',
            'occurred_at' => now(),
        ];
    }
}
