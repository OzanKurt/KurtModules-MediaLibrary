<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V3\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\ShareLinkResource\Pages;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

class ShareLinkResource extends Resource
{
    protected static ?string $model = ShareLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $recordTitleAttribute = 'token';

    protected static ?string $navigationLabel = 'Share links';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Share link')
                    ->description('Share links are created via the MediaLibrary facade. This screen is read-only; use the Revoke action to disable a link.')
                    ->schema([
                        TextInput::make('token')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        TextInput::make('invitee_email')
                            ->label('Invitee email')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('access_count')
                            ->label('Access count')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('token')
                    ->label('Token')
                    ->limit(12)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('item.title')
                    ->label('Item')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('folder.name')
                    ->label('Folder')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('abilities')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state)
                    ->color('info'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('access_count')
                    ->label('Accesses')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('revoked_at')
                    ->label('Revoked')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('revoked')
                    ->label('Revoked')
                    ->nullable()
                    ->attribute('revoked_at'),
                Filter::make('active')
                    ->label('Active only')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('revoked_at')
                        ->where(fn (Builder $q) => $q
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now()))),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ShareLink $record): bool => $record->revoked_at === null)
                    ->action(fn (ShareLink $record) => $record->update(['revoked_at' => now()])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShareLinks::route('/'),
            'view' => Pages\ViewShareLink::route('/{record}'),
        ];
    }
}
