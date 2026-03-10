<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubSubcategoryResource\Pages;
use App\Models\Sub_Subcategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use App\Traits\HasNavigationBadge;

class SubSubcategoryResource extends Resource
{
    use HasNavigationBadge;
    
    private static array $dataCache = [];

    protected static ?string $model = Sub_Subcategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Управление каталогом';
    protected static ?string $navigationLabel = 'Под-подкатегории';

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
                Forms\Components\Tabs::make('Под-подкатегория')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Основная информация')
                            ->schema([
                                Forms\Components\Section::make('Данные')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('subcategory_id')
                                                    ->label('Родительская подкатегория')
                                                    ->relationship('subcategory', 'name')
                                                    ->required()
                                                    ->searchable()
                                                    ->preload(),
                                                
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Название')
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
                                                    ->directory('category-icons/sub-subcategories')
                                                    ->visibility('public')
                                                    ->maxSize(1024)
                                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg']),
                                            ]),
                                        
                                        Forms\Components\Placeholder::make('full_path')
                                            ->label('Полный путь')
                                            ->content(function ($record) {
                                                if (!$record) return '—';
                                                $data = self::getData($record);
                                                return $data['category_name'] . ' → ' . $data['subcategory_name'] . ' → ' . $data['name'];
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Товары')
                            ->schema([
                                Forms\Components\Select::make('products')
                                    ->label('Товары в категории')
                                    ->multiple()
                                    ->relationship('products', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
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
                
                TextColumn::make('subcategory_name')
                    ->label('Подкатегория')
                    ->getStateUsing(fn ($record) => self::getData($record)['subcategory_name'])
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('category_name')
                    ->label('Категория')
                    ->getStateUsing(fn ($record) => self::getData($record)['category_name'])
                    ->searchable()
                    ->sortable(),
                
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
                    ->relationship('subcategory.category', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('subcategory')
                    ->label('Подкатегория')
                    ->relationship('subcategory', 'name')
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
            'index' => Pages\ListSubSubcategories::route('/'),
            'create' => Pages\CreateSubSubcategory::route('/create'),
            'edit' => Pages\EditSubSubcategory::route('/{record}/edit'),
            'view' => Pages\ViewSubSubcategory::route('/{record}'),
        ];
    }
}