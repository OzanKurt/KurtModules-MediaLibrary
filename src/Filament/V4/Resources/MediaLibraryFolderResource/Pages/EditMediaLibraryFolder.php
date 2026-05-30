<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryFolderResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryFolderResource;

class EditMediaLibraryFolder extends EditRecord
{
    protected static string $resource = MediaLibraryFolderResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
