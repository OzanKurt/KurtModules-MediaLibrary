<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V5\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Filament\V5\Resources\MediaLibraryItemResource\Pages;

class MediaLibraryItemResource extends Resource
{
    protected static ?string $model = MediaLibraryItem::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Media';

    /** @var array<int, string> */
    protected static array $locales = ['en', 'tr'];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Metadata')
                    ->schema([
                        Tabs::make('translations')
                            ->tabs(array_map(
                                fn (string $locale): Tab => Tab::make(strtoupper($locale))
                                    ->schema([
                                        TextInput::make("title.{$locale}")
                                            ->label('Title')
                                            ->required($locale === 'en')
                                            ->maxLength(255),
                                        TextInput::make("alt_text.{$locale}")
                                            ->label('Alt text')
                                            ->maxLength(255),
                                        TextInput::make("caption.{$locale}")
                                            ->label('Caption')
                                            ->maxLength(255),
                                        Textarea::make("description.{$locale}")
                                            ->label('Description')
                                            ->rows(3),
                                    ]),
                                static::$locales,
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Placement')
                    ->schema([
                        Select::make('folder_id')
                            ->relationship('folder', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Folder')
                            ->placeholder('Unfiled'),
                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->label('Tags'),
                    ])
                    ->columns(2),

                Section::make('Focal point')
                    ->description('Normalised 0..1 coordinates used for focal-point-aware cropping.')
                    ->schema([
                        TextInput::make('focal_x')
                            ->label('Focal X')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.001)
                            ->default(0.5),
                        TextInput::make('focal_y')
                            ->label('Focal Y')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.001)
                            ->default(0.5),
                    ])
                    ->columns(2),

                Section::make('File')
                    ->description('Stored file details (read-only). Replace the file via the MediaLibrary facade.')
                    ->schema([
                        TextInput::make('filename')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('mime_type')
                            ->label('MIME type')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('byte_size')
                            ->label('Size (bytes)')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('dominant_color')
                            ->label('Dominant color')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('preview_url')
                            ->label('File URL')
                            ->url()
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->formatStateUsing(fn (?MediaLibraryItem $record): ?string => $record?->url() ?: null)
                            ->visible(fn (Get $get, ?MediaLibraryItem $record): bool => $record !== null),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('byte_size')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => static::humanBytes($state))
                    ->sortable(),
                TextColumn::make('folder.name')
                    ->label('Folder')
                    ->placeholder('Unfiled')
                    ->toggleable(),
                TextColumn::make('view_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('download_count')
                    ->label('Downloads')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('mime_type')
                    ->label('Type')
                    ->options([
                        'image/jpeg' => 'JPEG',
                        'image/png' => 'PNG',
                        'image/webp' => 'WebP',
                        'image/gif' => 'GIF',
                        'video/mp4' => 'MP4',
                        'audio/mpeg' => 'MP3',
                        'application/pdf' => 'PDF',
                    ]),
                SelectFilter::make('folder_id')
                    ->label('Folder')
                    ->relationship('folder', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaLibraryItems::route('/'),
            'edit' => Pages\EditMediaLibraryItem::route('/{record}/edit'),
        ];
    }
}
