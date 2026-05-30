<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryFolderResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryFolderResource;

class CreateMediaLibraryFolder extends CreateRecord
{
    protected static string $resource = MediaLibraryFolderResource::class;
}
