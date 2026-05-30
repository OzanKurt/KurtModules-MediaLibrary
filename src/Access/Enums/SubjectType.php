<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Enums;

enum SubjectType: string
{
    case User = 'user';
    case Role = 'role';
    case Everyone = 'everyone';
}
