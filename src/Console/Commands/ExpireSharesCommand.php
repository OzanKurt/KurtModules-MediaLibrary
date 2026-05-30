<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Sharing\Events\ShareLinkExpired;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class ExpireSharesCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:expire-shares';

    /** @var string */
    protected $description = 'Count expired share links and dispatch ShareLinkExpired events.';

    public function handle(): int
    {
        $links = ShareLink::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('revoked_at')
            ->get();

        foreach ($links as $link) {
            ShareLinkExpired::dispatch($link);
        }

        $count = $links->count();
        $this->info("{$count} share link(s) past expiry.");

        return self::SUCCESS;
    }
}
