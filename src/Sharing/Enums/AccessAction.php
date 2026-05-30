<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Enums;

enum AccessAction: string
{
    case View = 'view';
    case Download = 'download';
    case Upload = 'upload';
    case Replace = 'replace';
    case Delete = 'delete';
}
