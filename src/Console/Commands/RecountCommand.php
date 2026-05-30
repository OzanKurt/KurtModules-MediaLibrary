<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

final class RecountCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:recount {owner?}';

    /** @var string */
    protected $description = 'Rebuild folder item_count + descendant_count.';

    public function handle(): int
    {
        $query = MediaLibraryFolder::query();

        if ($this->argument('owner')) {
            $query->where('owner_id', $this->argument('owner'));
        }

        $folders = $query->get();

        $count = 0;
        foreach ($folders as $folder) {
            $folder->forceFill([
                'item_count' => $folder->items()->count(),
                'descendant_count' => MediaLibraryFolder::query()
                    ->where('path', 'like', $folder->path.'/%')
                    ->count(),
            ])->save();
            $count++;
        }

        $this->info("Recounted {$count} folder(s).");

        return self::SUCCESS;
    }
}
