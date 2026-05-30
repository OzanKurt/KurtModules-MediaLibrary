<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;

final class PruneVariantsCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:prune-variants {item?}';

    /** @var string */
    protected $description = 'Drop unused variants past the configured unused_days retention.';

    public function handle(): int
    {
        $unusedDays = (int) config('media-library.variants.unused_days', 30);
        $cutoff = now()->subDays($unusedDays);

        $query = MediaLibraryVariant::query();

        if ($this->argument('item')) {
            $query->where('item_id', $this->argument('item'));
        }

        $query->where(function (Builder $q) use ($cutoff): void {
            $q->where('last_used_at', '<', $cutoff)
                ->orWhere(function (Builder $w) use ($cutoff): void {
                    $w->whereNull('last_used_at')
                        ->where('generated_at', '<', $cutoff);
                });
        });

        $pruned = $query->delete();

        $this->info("Pruned {$pruned} variant(s).");

        return self::SUCCESS;
    }
}
