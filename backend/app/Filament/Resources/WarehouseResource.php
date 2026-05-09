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
                    ->whereColumn('warehouse_id', 'warehouses.id')
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
                                
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true),
                                
                                Forms\Components\Toggle::make('is_supplier')
                                    ->label('Является поставщиком')
                                    ->default(false)
                                    ->reactive(),
                            ]),
                    ]),

                Forms\Components\Section::make('Параметры заказа у поставщика')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_order_quantity')
                                    ->label('Минимальный заказ')
                                    ->numeric()
                                    ->default(1)
                                    ->suffix('шт.'),
                                
                                Forms\Components\TextInput::make('lead_time_days')
                                    ->label('Срок поставки')
                                    ->numeric()
                                    ->default(7)
                                    ->suffix('дней'),
                                
                                Forms\Components\TextInput::make('consolidation_period')
                                    ->label('Период консолидации')
                                    ->numeric()
                                    ->default(3)
                                    ->suffix('дня'),
                                
                                Forms\Components\KeyValue::make('order_schedule')
                                    ->label('График заказов')
                                    ->keyLabel('Параметр')
                                    ->valueLabel('Значение')
                                    ->default(['days' => [8, 18, 28], 'type' => 'monthly']),
                            ]),
                    ])
                    ->visible(fn ($get) => $get('is_supplier') === true),
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

                IconColumn::make('is_supplier')
                    ->label('Поставщик')
                    ->boolean()
                    ->trueIcon('heroicon-o-truck')
                    ->falseIcon('heroicon-o-building-storefront')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                // 👈 ИСПРАВЛЕНО: теперь сортировка работает через withCount
                TextColumn::make('inventories_count')
                    ->label('Товаров на складе')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
                // 👈 ИСПРАВЛЕНО: теперь сортировка работает через addSelect
                TextColumn::make('total_quantity')
                    ->label('Единиц товара')
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
                
                Filter::make('is_supplier')
                    ->label('Только поставщики')
                    ->query(fn (Builder $query): Builder => $query->where('is_supplier', true)),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                
                Filter::make('has_stock')
                    ->label('Есть товары')
                    ->query(fn (Builder $query): Builder => $query->whereHas('inventories', fn ($q) => $q->where('quantity', '>', 0))),
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