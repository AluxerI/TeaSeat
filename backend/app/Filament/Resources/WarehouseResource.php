<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Склад и поставщики';

    protected static ?string $navigationLabel = 'Склады';

    protected static ?string $modelLabel = 'Склад';

    protected static ?string $pluralModelLabel = 'Склады';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Основная информация')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Название склада')
                                    ->required()
                                    ->maxLength(255),
                                
                                Select::make('city')
                                    ->label('Город')
                                    ->options([
                                        'Москва' => 'Москва',
                                        'Санкт-Петербург' => 'Санкт-Петербург',
                                        'Новосибирск' => 'Новосибирск',
                                        'Тула' => 'Тула',
                                        'Казань' => 'Казань',
                                        'Екатеринбург' => 'Екатеринбург',
                                        'Нижний Новгород' => 'Нижний Новгород',
                                        'Самара' => 'Самара',
                                        'Омск' => 'Омск',
                                        'Челябинск' => 'Челябинск',
                                        'Ростов-на-Дону' => 'Ростов-на-Дону',
                                        'Уфа' => 'Уфа',
                                        'Красноярск' => 'Красноярск',
                                        'Воронеж' => 'Воронеж',
                                        'Пермь' => 'Пермь',
                                        'Волгоград' => 'Волгоград',
                                    ])
                                    ->searchable()
                                    ->required(),
                                
                                TextInput::make('location')
                                    ->label('Адрес / Расположение')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                
                                Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true)
                                    ->helperText('Неактивные склады не участвуют в отгрузках'),
                                
                                Toggle::make('is_supplier')
                                    ->label('Является поставщиком')
                                    ->default(false)
                                    ->helperText('Используется для заказов у поставщиков')
                                    ->reactive(),
                            ]),
                    ]),

                Section::make('Параметры заказа у поставщика')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_order_quantity')
                                    ->label('Минимальный заказ')
                                    ->numeric()
                                    ->default(1)
                                    ->suffix('шт.'),
                                
                                TextInput::make('lead_time_days')
                                    ->label('Срок поставки')
                                    ->numeric()
                                    ->default(7)
                                    ->suffix('дней'),
                                
                                TextInput::make('consolidation_period')
                                    ->label('Период консолидации')
                                    ->numeric()
                                    ->default(3)
                                    ->suffix('дня'),
                                
                                Forms\Components\KeyValue::make('order_schedule')
                                    ->label('График заказов')
                                    ->keyLabel('Параметр')
                                    ->valueLabel('Значение')
                                    ->default(['days' => [8, 18, 28], 'type' => 'monthly'])
                                    ->helperText('Например: {"days":[8,18,28],"type":"monthly"}'),
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
                    ->toggleable(),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                
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
                
                TextColumn::make('inventories_count')
                    ->label('Товаров на складе')
                    ->counts('inventories')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('total_quantity')
                    ->label('Единиц товара')
                    ->getStateUsing(fn ($record) => $record->inventories->sum('quantity'))
                    ->sortable()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('city')
                    ->label('Город')
                    ->options([
                        'Москва' => 'Москва',
                        'Санкт-Петербург' => 'Санкт-Петербург',
                        'Новосибирск' => 'Новосибирск',
                        'Тула' => 'Тула',
                    ])
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
            ->defaultSort('city', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count() ?: null;
    }
}