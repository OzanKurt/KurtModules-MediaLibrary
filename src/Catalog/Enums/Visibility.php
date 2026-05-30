<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Enums;

enum Visibility: string
{
    case Private = 'private';
    case Restricted = 'restricted';
    case Public = 'public';
}
