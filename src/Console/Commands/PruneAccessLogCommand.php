<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Sharing\Models\AccessLogEntry;

/**
 * Enforces access-log retention (GDPR storage limitation): hard-deletes log
 * entries older than `media-library.access_log.prune_after_days`. Mirrors the
 * share-link prune command. A retention of 0 disables pruning entirely.
 */
final class PruneAccessLogCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:prune-access-log';

    /** @var string */
    protected $description = 'Delete access-log entries older than the configured retention.';

    public function handle(): int
    {
        $days = (int) config('media-library.access_log.prune_after_days', 365);

        if ($days <= 0) {
            $this->info('Access-log pruning is disabled (prune_after_days <= 0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $count = AccessLogEntry::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$count} access-log entrie(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
