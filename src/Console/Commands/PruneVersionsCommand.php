<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Events\VersionPruned;

final class PruneVersionsCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:prune-versions {item?}';

    /** @var string */
    protected $description = 'Drop versions older than the configured keep_old retention.';

    public function handle(): int
    {
        $keepNewest = (int) config('media-library.versions.keep_old', 10);

        $items = $this->argument('item')
            ? MediaLibraryItem::query()->whereKey($this->argument('item'))->get()
            : MediaLibraryItem::query()->get();

        $pruned = 0;
        foreach ($items as $item) {
            $versions = $item->versions()
                ->orderByDesc('created_at')
                ->skip($keepNewest)
                ->take(1000)
                ->get();

            foreach ($versions as $version) {
                VersionPruned::dispatch($version);
                $version->delete();
                $pruned++;
            }
        }

        $this->info("Pruned {$pruned} version(s).");

        return self::SUCCESS;
    }
}
