<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Enums;

enum ItemKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Archive = 'archive';
    case Other = 'other';
}
