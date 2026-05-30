<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryTagResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryTagResource;

class EditMediaLibraryTag extends EditRecord
{
    protected static string $resource = MediaLibraryTagResource::class;

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
