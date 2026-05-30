<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;

final class ExpirePendingUploadsCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:expire-pending-uploads';

    /** @var string */
    protected $description = 'Mark pending uploads past their expires_at as expired.';

    public function handle(): int
    {
        $count = MediaLibraryPendingUpload::query()
            ->where('status', PendingUploadStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update(['status' => PendingUploadStatus::Expired->value]);

        $this->info("Expired {$count} pending upload(s).");

        return self::SUCCESS;
    }
}
