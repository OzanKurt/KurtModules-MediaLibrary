<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Enums;

enum AttachmentRole: string
{
    case Cover = 'cover';
    case Social = 'social';
    case Gallery = 'gallery';
    case Thumbnail = 'thumbnail';
    case Attachment = 'attachment';
    case Hero = 'hero';
    case Logo = 'logo';
    case Favicon = 'favicon';
}
