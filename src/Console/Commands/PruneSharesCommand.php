<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class PruneSharesCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:prune-shares';

    /** @var string */
    protected $description = 'Hard-delete expired share links beyond the configured retention.';

    public function handle(): int
    {
        $days = (int) config('media-library.share_links.prune_after_days', 30);
        $cutoff = now()->subDays($days);

        $count = ShareLink::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $cutoff)
            ->forceDelete();

        $this->info("Pruned {$count} expired share link(s).");

        return self::SUCCESS;
    }
}
