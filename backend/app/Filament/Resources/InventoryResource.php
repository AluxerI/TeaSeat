<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;

class InventoryResource extends Resource
{
    use HasNavigationBadge;
    
    private static array $dataCache = [];

    protected static ?string $model = Inventory::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Склад и поставщики';
    protected static ?string $navigationLabel = 'Остатки';

    private static function getData($record): array
    {
        $key = $record->product_id . '-' . $record->warehouse_id;
        if (!isset(self::$dataCache[$key])) {
            self::$dataCache[$key] = [
                'product_name' => $record->product?->name,
                'product_price' => $record->product?->price,
                'warehouse_name' => $record->warehouse?->name,
                'warehouse_city' => $record->warehouse?->city,
                'quantity' => $record->quantity,
                'last_restock_date' => $record->last_restock_date 
                    ? (is_string($record->last_restock_date) 
                        ? $record->last_restock_date 
                        : $record->last_restock_date->format('d.m.Y'))
                    : null,
            ];
        }
        return self::$dataCache[$key];
    }

    public static function getRecordRouteKeyName(): ?string
    {
        return 'inventory_key';
    }

    public static function resolveRecordRouteBinding(int | string $key): ?Model
    {
        $query = parent::resolveRecordRouteBinding($key);
        
        if ($query) {
            return $query;
        }

        $parts = explode('-', $key);
        if (count($parts) === 2) {
            return Inventory::where('product_id', $parts[0])
                ->where('warehouse_id', $parts[1])
                ->first();
        }

        return null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация об остатке')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('warehouse_id')
                                    ->label('Склад')
                                    ->relationship('warehouse', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn ($record) => $record !== null),
                                
                                Forms\Components\Select::make('product_id')
                                    ->label('Товар')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn ($record) => $record !== null),
                                
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Количество')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->suffix('шт.')
                                    ->afterStateUpdated(function ($state, $record) {
                                        if ($record) {
                                            $record->product?->updateCacheFields();
                                        }
                                    }),
                                
                                Forms\Components\DatePicker::make('last_restock_date')
                                    ->label('Дата последней поставки')
                                    ->nullable()
                                    ->maxDate(now()),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')
                    ->label('Товар')
                    ->getStateUsing(fn ($record) => self::getData($record)['product_name'])
                    ->searchable(query: fn ($query, $search) => $query->whereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy(
                        Product::select('name')->whereColumn('products.id', 'inventories.product_id'), $direction
                    ))
                    ->limit(40),
                
                TextColumn::make('product_price')
                    ->label('Цена')
                    ->getStateUsing(fn ($record) => self::getData($record)['product_price'])
                    ->money('RUB')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('warehouse_name')
                    ->label('Склад')
                    ->getStateUsing(fn ($record) => self::getData($record)['warehouse_name'])
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('warehouse_city')
                    ->label('Город')
                    ->getStateUsing(fn ($record) => self::getData($record)['warehouse_city'])
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('quantity')
                    ->label('Количество')
                    ->sortable()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight(fn ($state) => $state > 0 ? 'bold' : 'normal'),
                
                TextColumn::make('last_restock_date')
                    ->label('Последняя поставка')
                    ->getStateUsing(fn ($record) => self::getData($record)['last_restock_date'])
                    ->sortable()
                    ->toggleable()
                    ->color(function ($record) {
                        if (!$record->last_restock_date) return 'default';

                        $date = $record->last_restock_date instanceof \Carbon\Carbon 
                            ? $record->last_restock_date 
                            : \Carbon\Carbon::parse($record->last_restock_date);

                        return $date->lt(now()->subDays(30)) ? 'warning' : 'default';
                    }),
                
                TextColumn::make('created_at')
                    ->label('Запись создана')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse')
                    ->label('Склад')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('city')
                    ->label('Город')
                    ->relationship('warehouse', 'city')
                    ->searchable()
                    ->multiple(),
                
                SelectFilter::make('product')
                    ->label('Товар')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->optionsLimit(100),
                
                Filter::make('in_stock')
                    ->label('В наличии')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '>', 0)),
                
                Filter::make('out_of_stock')
                    ->label('Нет в наличии')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '<=', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Inventory $record): string => static::getUrl('view', [
                        'record' => $record->product_id . '-' . $record->warehouse_id
                    ])),
                
                Tables\Actions\EditAction::make()
                    ->url(fn (Inventory $record): string => static::getUrl('edit', [
                        'record' => $record->product_id . '-' . $record->warehouse_id
                    ])),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_restock_date', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'view' => Pages\ViewInventory::route('/{record}'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product', 'warehouse']);
    }
}