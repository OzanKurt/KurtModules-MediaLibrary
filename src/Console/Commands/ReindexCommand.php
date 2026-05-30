<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Search\Contracts\ScoutAdapter;

final class ReindexCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:reindex {owner?}';

    /** @var string */
    protected $description = 'Push media library items to the bound ScoutAdapter.';

    public function handle(): int
    {
        $scoutClass = config('media-library.contracts.scout');

        if (! is_string($scoutClass) || $scoutClass === '') {
            $this->warn('No ScoutAdapter configured.');

            return self::SUCCESS;
        }

        /** @var ScoutAdapter $scout */
        $scout = app($scoutClass);

        $query = MediaLibraryItem::query();
        if ($this->argument('owner')) {
            $query->where('owner_id', $this->argument('owner'));
        }

        $count = 0;
        foreach ($query->cursor() as $item) {
            $scout->index($item);
            $count++;
        }

        $this->info("Reindexed {$count} item(s).");

        return self::SUCCESS;
    }
}
