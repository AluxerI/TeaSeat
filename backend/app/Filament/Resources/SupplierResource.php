<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
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

class SupplierResource extends Resource
{
    use HasNavigationBadge;
    
    private static array $dataCache = [];

    protected static ?string $model = Supplier::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Склад и поставщики';
    protected static ?string $navigationLabel = 'Поставщики';

    private static function getData($record): array
    {
        $id = $record->id;
        if (!isset(self::$dataCache[$id])) {
            self::$dataCache[$id] = [
                'name' => $record->name,
                'contact_person' => $record->contact_person,
                'city' => $record->city,
                'is_active' => $record->is_active,
                'products_count' => $record->products()->count(),
                'active_products_count' => $record->products()->wherePivot('is_active', true)->count(),
                'orders_count' => $record->supplierOrders()->count(),
                'lead_time_days' => $record->lead_time_days,
                'created_at' => $record->created_at?->format('d.m.Y'),
            ];
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
                                Forms\Components\TextInput::make('name')
                                    ->label('Название компании')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('contact_person')
                                    ->label('Контактное лицо')
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('phone')
                                    ->label('Телефон')
                                    ->tel()
                                    ->maxLength(20),
                                
                                Forms\Components\TextInput::make('city')
                                    ->label('Город')
                                    ->maxLength(255),
                                
                                Forms\Components\Textarea::make('address')
                                    ->label('Адрес')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true),
                            ]),
                    ]),

                Forms\Components\Section::make('Параметры заказа')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('lead_time_days')
                                    ->label('Срок поставки')
                                    ->numeric()
                                    ->default(7)
                                    ->suffix('дней'),
                                
                                Forms\Components\TextInput::make('min_order_quantity')
                                    ->label('Минимальный заказ')
                                    ->numeric()
                                    ->default(1)
                                    ->suffix('шт.'),
                                
                                Forms\Components\TextInput::make('consolidation_period')
                                    ->label('Период консолидации')
                                    ->numeric()
                                    ->default(3)
                                    ->suffix('дня'),
                                
                                Forms\Components\KeyValue::make('order_schedule')
                                    ->label('График заказов')
                                    ->keyLabel('Параметр')
                                    ->valueLabel('Значение')
                                    ->default(['days' => [8, 18, 28], 'type' => 'monthly'])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Forms\Components\Section::make('Статистика')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('products_count')
                                    ->label('Товаров')
                                    ->content(fn ($record) => $record ? self::getData($record)['products_count'] : 0),
                                
                                Forms\Components\Placeholder::make('active_products_count')
                                    ->label('Активных товаров')
                                    ->content(fn ($record) => $record ? self::getData($record)['active_products_count'] : 0),
                                
                                Forms\Components\Placeholder::make('orders_count')
                                    ->label('Заказов поставщику')
                                    ->content(fn ($record) => $record ? self::getData($record)['orders_count'] : 0),
                            ])
                            ->visible(fn ($record) => $record !== null),
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
                    ->label('Поставщик')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('contact_person')
                    ->label('Контакт')
                    ->getStateUsing(fn ($record) => self::getData($record)['contact_person'])
                    ->searchable()
                    ->toggleable(),
                
                TextColumn::make('city')
                    ->label('Город')
                    ->getStateUsing(fn ($record) => self::getData($record)['city'])
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->getStateUsing(fn ($record) => self::getData($record)['products_count'])
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->getStateUsing(fn ($record) => self::getData($record)['is_active'])
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                TextColumn::make('lead_time_days')
                    ->label('Срок')
                    ->getStateUsing(fn ($record) => self::getData($record)['lead_time_days'])
                    ->suffix(' дн.')
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->getStateUsing(fn ($record) => self::getData($record)['created_at'])
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('city')
                    ->label('Город')
                    ->options(fn () => Supplier::distinct()->pluck('city', 'city')->filter()->toArray())
                    ->multiple(),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                
                Filter::make('has_products')
                    ->label('Есть товары')
                    ->query(fn (Builder $query): Builder => $query->whereHas('products')),
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
            'view' => Pages\ViewSupplier::route('/{record}'),
        ];
    }
}