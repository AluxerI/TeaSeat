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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Склад и поставщики';

    protected static ?string $navigationLabel = 'Поставщики';

    protected static ?string $modelLabel = 'Поставщик';

    protected static ?string $pluralModelLabel = 'Поставщики';

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
                                    ->label('Название компании')
                                    ->required()
                                    ->maxLength(255),
                                
                                TextInput::make('contact_person')
                                    ->label('Контактное лицо')
                                    ->maxLength(255),
                                
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                
                                TextInput::make('phone')
                                    ->label('Телефон')
                                    ->tel()
                                    ->maxLength(20),
                                
                                TextInput::make('city')
                                    ->label('Город')
                                    ->maxLength(255),
                                
                                Textarea::make('address')
                                    ->label('Адрес')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                
                                Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true)
                                    ->helperText('Неактивные поставщики не участвуют в заказах'),
                            ]),
                    ]),

                Section::make('Параметры заказа')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('lead_time_days')
                                    ->label('Срок поставки')
                                    ->numeric()
                                    ->default(7)
                                    ->suffix('дней'),
                                
                                TextInput::make('min_order_quantity')
                                    ->label('Минимальный заказ')
                                    ->numeric()
                                    ->default(1)
                                    ->suffix('шт.'),
                                
                                TextInput::make('consolidation_period')
                                    ->label('Период консолидации')
                                    ->numeric()
                                    ->default(3)
                                    ->suffix('дня'),
                                
                                KeyValue::make('order_schedule')
                                    ->label('График заказов')
                                    ->keyLabel('Параметр')
                                    ->valueLabel('Значение')
                                    ->default(['days' => [8, 18, 28], 'type' => 'monthly'])
                                    ->helperText('Например: {"days":[8,18,28],"type":"monthly"}')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Статистика')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('products_count')
                                    ->label('Товаров')
                                    ->content(fn ($record) => $record?->products()->count() ?? 0),
                                
                                Forms\Components\Placeholder::make('active_products_count')
                                    ->label('Активных товаров')
                                    ->content(fn ($record) => $record?->products()->wherePivot('is_active', true)->count() ?? 0),
                                
                                Forms\Components\Placeholder::make('orders_count')
                                    ->label('Заказов поставщику')
                                    ->content(fn ($record) => $record?->supplierOrders()->count() ?? 0),
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
                    ->toggleable(),
                
                TextColumn::make('name')
                    ->label('Поставщик')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('contact_person')
                    ->label('Контакт')
                    ->searchable()
                    ->toggleable(),
                
                TextColumn::make('city')
                    ->label('Город')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->counts('products')
                    ->sortable()
                    ->alignCenter(),
                
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                TextColumn::make('lead_time_days')
                    ->label('Срок')
                    ->suffix(' дн.')
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('city')
                    ->label('Город')
                    ->options(function () {
                        return Supplier::distinct()->pluck('city', 'city')->filter()->toArray();
                    })
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
            ->defaultSort('name', 'asc');
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
            'view' => Pages\ViewSupplier::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count() ?: null;
    }
}