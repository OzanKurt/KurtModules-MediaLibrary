<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Enums;

enum PendingUploadStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
