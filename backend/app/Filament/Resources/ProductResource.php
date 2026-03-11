<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;

class ProductResource extends Resource
{
    use HasNavigationBadge;
    
    private static array $dataCache = [];

    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Управление товарами';
    protected static ?string $navigationLabel = 'Товары';

    /**
     * Получить данные товара с кешированием в памяти
     */
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
                // ... форма (без изменений)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ID - скрыт по умолчанию
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                // Фото товара (используем main_image_url)
                ImageColumn::make('main_image')
                    ->label('Фото')
                    ->circular()
                    ->getStateUsing(function ($record) {
                        $data = self::getData($record);
                        return $data['main_image_url'] ?? null;
                    })
                    ->defaultImageUrl(url('/images/default-product.jpg')),
                
                // Название товара
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),
                
                // Цена
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                
                // Путь категории
                TextColumn::make('category_path')
                    ->label('Категория')
                    ->getStateUsing(function ($record) {
                        $data = self::getData($record);
                        $path = $data['category_path'] ?? null;
                        if ($path) {
                            return $path['category'] . ' → ' . 
                                   $path['subcategory'] . ' → ' . 
                                   $path['sub_subcategory'];
                        }
                        return '—';
                    })
                    ->toggleable(),
                
                // Остаток на складе
                TextColumn::make('total_quantity')
                    ->label('Остаток')
                    ->getStateUsing(fn ($record) => self::getData($record)['total_quantity'])
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->badge()
                    ->alignCenter(),
                
                // Продано
                TextColumn::make('sold_count')
                    ->label('Продано')
                    ->getStateUsing(fn ($record) => self::getData($record)['sold_count'])
                    ->sortable()
                    ->toggleable(),
                
                // Дата создания
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->getStateUsing(fn ($record) => self::getData($record)['created_at'])
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Категория')
                    ->relationship('sub_subcategories.subcategory.category', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('brand')
                    ->label('Бренд')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('in_stock')
                    ->label('В наличии')
                    ->query(fn (Builder $query): Builder => $query->whereHas('inventories', fn ($q) => $q->where('quantity', '>', 0))),
                
                Filter::make('out_of_stock')
                    ->label('Нет в наличии')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('inventories', fn ($q) => $q->where('quantity', '>', 0))),
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
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }
}