<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V4\Resources\ShareLinkResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\ShareLinkResource;

class ListShareLinks extends ListRecords
{
    protected static string $resource = ShareLinkResource::class;
}
