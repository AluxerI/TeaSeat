<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use App\Models\Inventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;

class WarehouseResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Warehouse::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Склад и поставщики';
    protected static ?string $navigationLabel = 'Склады';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['inventories'])
            ->withCount(['inventories']) // 👈 Добавляем подсчет количества товаров
            ->addSelect([
                'total_quantity' => Inventory::selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('warehouse_id', 'warehouses.id'),
                'total_available_quantity' => Inventory::selectRaw(
                    'COALESCE(SUM(' . Inventory::ONLINE_AVAILABLE_EXPRESSION . '), 0)'
                )->whereColumn('warehouse_id', 'warehouses.id'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название склада')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\Select::make('city')
                                    ->label('Город')
                                    ->options([
                                        'Москва' => 'Москва',
                                        'Санкт-Петербург' => 'Санкт-Петербург',
                                        'Новосибирск' => 'Новосибирск',
                                        'Тула' => 'Тула',
                                        'Казань' => 'Казань',
                                        'Екатеринбург' => 'Екатеринбург',
                                    ])
                                    ->searchable()
                                    ->required(),
                                
                                Forms\Components\TextInput::make('location')
                                    ->label('Адрес / Расположение')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('type')
                                    ->label('Тип точки')
                                    ->options([
                                        Warehouse::TYPE_WAREHOUSE => 'Склад',
                                        Warehouse::TYPE_STORE => 'Магазин',
                                    ])
                                    ->default(Warehouse::TYPE_WAREHOUSE)
                                    ->required(),
                                
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true),
                                
                                Forms\Components\Toggle::make('is_online_fulfillment_enabled')
                                    ->label('Можно собирать интернет-заказы')
                                    ->helperText('Остатки точки участвуют в доступности интернет-магазина')
                                    ->default(true),
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('city')
                    ->label('Город')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Warehouse::TYPE_STORE => 'Магазин',
                        default => 'Склад',
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === Warehouse::TYPE_STORE ? 'info' : 'gray'),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('is_online_fulfillment_enabled')
                    ->label('Онлайн-заказы')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                // 👈 ИСПРАВЛЕНО: теперь сортировка работает через withCount
                TextColumn::make('inventories_count')
                    ->label('Товаров на складе')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
                // 👈 ИСПРАВЛЕНО: теперь сортировка работает через addSelect
                TextColumn::make('total_quantity')
                    ->label('Физический остаток')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('total_available_quantity')
                    ->label('Свободный остаток')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('city')
                    ->label('Город')
                    ->options(fn () => Warehouse::distinct()->whereNotNull('city')->pluck('city', 'city')->toArray())
                    ->multiple(),
                
                SelectFilter::make('type')
                    ->label('Тип точки')
                    ->options([
                        Warehouse::TYPE_WAREHOUSE => 'Склад',
                        Warehouse::TYPE_STORE => 'Магазин',
                    ]),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                
                Filter::make('has_stock')
                    ->label('Есть товары')
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'inventories',
                        fn ($q) => $q->whereRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' > 0')
                    )),
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
            ->defaultSort('city', 'asc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'view' => Pages\ViewWarehouse::route('/{record}'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
