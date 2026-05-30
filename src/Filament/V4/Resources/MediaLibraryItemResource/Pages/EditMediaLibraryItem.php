<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryItemResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryItemResource;

class EditMediaLibraryItem extends EditRecord
{
    protected static string $resource = MediaLibraryItemResource::class;

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
