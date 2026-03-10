<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
// use App\Traits\HasNavigationBadge;

class BrandResource extends Resource
{
    // use HasNavigationBadge;
    
    private static array $brandDataCache = [];

    protected static ?string $model = Brand::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Управление каталогом';
    protected static ?string $navigationLabel = 'Бренды';

    private static function getBrandData($record): array
    {
        $id = $record->id;
        if (!isset(self::$brandDataCache[$id])) {
            self::$brandDataCache[$id] = $record->getAllData();
        }
        return self::$brandDataCache[$id];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация о бренде')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название бренда')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('country')
                                    ->label('Страна')
                                    ->maxLength(100)
                                    ->placeholder('Например: Россия, Китай, Индия'),
                                
                                Forms\Components\FileUpload::make('logo')
                                    ->label('Логотип бренда')
                                    ->image()
                                    ->directory('brands')
                                    ->visibility('public')
                                    ->maxSize(1024)
                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('country')
                    ->label('Страна')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->getStateUsing(fn ($record) => self::getBrandData($record)['products_count'])
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),
                
                TextColumn::make('total_sold')
                    ->label('Продано')
                    ->getStateUsing(fn ($record) => self::getBrandData($record)['total_sold'])
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->label('Страна')
                    ->options(fn () => Brand::distinct()->whereNotNull('country')->pluck('country', 'country'))
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}