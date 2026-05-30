<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V5\Resources\MediaLibraryFolderResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Kurt\Modules\MediaLibrary\Filament\V5\Resources\MediaLibraryFolderResource;

class ListMediaLibraryFolders extends ListRecords
{
    protected static string $resource = MediaLibraryFolderResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
