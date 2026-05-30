<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V3\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryFolderResource\Pages;

class MediaLibraryFolderResource extends Resource
{
    protected static ?string $model = MediaLibraryFolder::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Folders';

    /** @var array<int, string> */
    protected static array $locales = ['en', 'tr'];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Tabs::make('translations')
                            ->tabs(array_map(
                                fn (string $locale): Tab => Tab::make(strtoupper($locale))
                                    ->schema([
                                        TextInput::make("name.{$locale}")
                                            ->label('Name')
                                            ->required($locale === 'en')
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

                Section::make()
                    ->schema([
                        Select::make('parent_id')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Parent folder')
                            ->placeholder('Root'),
                        Select::make('visibility')
                            ->options(static::visibilityOptions())
                            ->default(Visibility::Private->value)
                            ->required(),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('path')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (Visibility $state): string => ucfirst($state->value))
                    ->color(fn (Visibility $state): string => match ($state) {
                        Visibility::Private => 'gray',
                        Visibility::Restricted => 'warning',
                        Visibility::Public => 'success',
                    })
                    ->sortable(),
                TextColumn::make('item_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Root')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->options(static::visibilityOptions()),
            ])
            ->defaultSort('path')
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

    /**
     * @return array<string, string>
     */
    protected static function visibilityOptions(): array
    {
        $options = [];

        foreach (Visibility::cases() as $case) {
            $options[$case->value] = ucfirst($case->value);
        }

        return $options;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaLibraryFolders::route('/'),
            'create' => Pages\CreateMediaLibraryFolder::route('/create'),
            'edit' => Pages\EditMediaLibraryFolder::route('/{record}/edit'),
        ];
    }
}
