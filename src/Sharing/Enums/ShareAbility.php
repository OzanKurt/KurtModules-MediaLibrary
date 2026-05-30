<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Enums;

enum ShareAbility: string
{
    case View = 'view';
    case Download = 'download';
}
