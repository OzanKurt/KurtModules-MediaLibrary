<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryTagResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryTagResource;

class ListMediaLibraryTags extends ListRecords
{
    protected static string $resource = MediaLibraryTagResource::class;

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
