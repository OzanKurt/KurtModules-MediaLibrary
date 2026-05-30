<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryTagResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryTagResource;

class CreateMediaLibraryTag extends CreateRecord
{
    protected static string $resource = MediaLibraryTagResource::class;
}
