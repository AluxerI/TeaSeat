<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubcategoryResource\Pages;
use App\Models\Subcategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use App\Traits\HasNavigationBadge;

class SubcategoryResource extends Resource
{
    use HasNavigationBadge;
    
    private static array $dataCache = [];

    protected static ?string $model = Subcategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Управление каталогом';
    protected static ?string $navigationLabel = 'Подкатегории';

    private static function getData($record): array
    {
        $id = $record->id;
        if (!isset(self::$dataCache[$id])) {
            self::$dataCache[$id] = $record->getAllData();
        }
        return self::$dataCache[$id];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('category_id')
                                    ->label('Родительская категория')
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                
                                Forms\Components\TextInput::make('name')
                                    ->label('Название подкатегории')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug())),
                                
                                Forms\Components\TextInput::make('slug')
                                    ->label('URL-алиас')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                
                                Forms\Components\FileUpload::make('icon')
                                    ->label('Иконка')
                                    ->image()
                                    ->directory('category-icons/subcategories')
                                    ->visibility('public')
                                    ->maxSize(1024)
                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg']),
                            ]),
                    ]),

                Forms\Components\Section::make('Под-подкатегории')
                    ->schema([
                        Forms\Components\Repeater::make('sub_subcategories')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Название')
                                            ->required()
                                            ->maxLength(255),
                                        
                                        Forms\Components\FileUpload::make('icon')
                                            ->label('Иконка')
                                            ->image()
                                            ->directory('category-icons/sub-subcategories')
                                            ->visibility('public')
                                            ->maxSize(1024),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('category_name')
                    ->label('Категория')
                    ->getStateUsing(fn ($record) => self::getData($record)['category_name'])
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('sub_subcategories_count')
                    ->label('Под-подкатегории')
                    ->getStateUsing(fn ($record) => self::getData($record)['sub_subcategories_count'])
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->getStateUsing(fn ($record) => self::getData($record)['products_count'])
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),
                
                ImageColumn::make('icon')
                    ->label('Иконка')
                    ->circular()
                    ->getStateUsing(fn ($record) => self::getData($record)['icon_url'])
                    ->defaultImageUrl(url('/images/default-icon.png')),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Категория')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubcategories::route('/'),
            'create' => Pages\CreateSubcategory::route('/create'),
            'edit' => Pages\EditSubcategory::route('/{record}/edit'),
            'view' => Pages\ViewSubcategory::route('/{record}'),
        ];
    }
}