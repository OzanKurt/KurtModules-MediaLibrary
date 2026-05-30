<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryItemResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryItemResource;

class ListMediaLibraryItems extends ListRecords
{
    protected static string $resource = MediaLibraryItemResource::class;
}
