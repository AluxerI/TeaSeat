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
use Filament\Notifications\Notification;

class SupplierResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Supplier::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Склад и поставщики';
    protected static ?string $navigationLabel = 'Поставщики';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['products', 'supplierOrders'])
            ->withCount(['products', 'supplierOrders']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Поставщик')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Основная информация')
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
                                                    ->maxLength(20)
                                                    ->placeholder('+7 (XXX) XXX-XX-XX'),
                                                
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
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Параметры заказа')
                            ->schema([
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
                                                
                                                // 👈 ИСПРАВЛЕНО: делаем поле проще
                                                Forms\Components\Fieldset::make('График заказов')
                                                    ->schema([
                                                        Forms\Components\Select::make('order_schedule_days')
                                                            ->label('Дни месяца')
                                                            ->multiple()
                                                            ->options([
                                                                1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5',
                                                                6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10',
                                                                11 => '11', 12 => '12', 13 => '13', 14 => '14', 15 => '15',
                                                                16 => '16', 17 => '17', 18 => '18', 19 => '19', 20 => '20',
                                                                21 => '21', 22 => '22', 23 => '23', 24 => '24', 25 => '25',
                                                                26 => '26', 27 => '27', 28 => '28', 29 => '29', 30 => '30',
                                                                31 => '31',
                                                            ])
                                                            ->default([8, 18, 28])
                                                            ->columnSpanFull()
                                                            ->afterStateHydrated(function ($component, $record) {
                                                                if ($record && $record->order_schedule) {
                                                                    $schedule = is_string($record->order_schedule) 
                                                                        ? json_decode($record->order_schedule, true) 
                                                                        : $record->order_schedule;
                                                                    $component->state($schedule['days'] ?? [8, 18, 28]);
                                                                }
                                                            })
                                                            ->dehydrated(false),
                                                        
                                                        Forms\Components\Select::make('order_schedule_type')
                                                            ->label('Тип расписания')
                                                            ->options([
                                                                'monthly' => 'Ежемесячно',
                                                                'weekly' => 'Еженедельно',
                                                            ])
                                                            ->default('monthly')
                                                            ->afterStateHydrated(function ($component, $record) {
                                                                if ($record && $record->order_schedule) {
                                                                    $schedule = is_string($record->order_schedule) 
                                                                        ? json_decode($record->order_schedule, true) 
                                                                        : $record->order_schedule;
                                                                    $component->state($schedule['type'] ?? 'monthly');
                                                                }
                                                            })
                                                            ->dehydrated(false),
                                                    ])
                                                    ->columnSpanFull(),
                                                
                                                // 👈 Скрытое поле для сохранения JSON
                                                Forms\Components\Hidden::make('order_schedule')
                                                    ->default(json_encode(['days' => [8, 18, 28], 'type' => 'monthly']))
                                                    ->dehydrateStateUsing(function ($state, $get) {
                                                        $days = $get('order_schedule_days') ?? [8, 18, 28];
                                                        $type = $get('order_schedule_type') ?? 'monthly';
                                                        return json_encode([
                                                            'days' => array_map('intval', $days),
                                                            'type' => $type
                                                        ]);
                                                    }),
                                            ]),
                                    ]),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Статистика')
                            ->schema([
                                Forms\Components\Section::make('Статистика')
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\Placeholder::make('products_count')
                                                    ->label('Товаров')
                                                    ->content(fn ($record) => $record?->products_count ?? 0),
                                                
                                                Forms\Components\Placeholder::make('orders_count')
                                                    ->label('Заказов поставщику')
                                                    ->content(fn ($record) => $record?->supplier_orders_count ?? 0),
                                            ])
                                            ->visible(fn ($record) => $record !== null),
                                    ]),
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
                    ->label('Поставщик')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('contact_person')
                    ->label('Контакт')
                    ->searchable()
                    ->toggleable(),
                
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->toggleable(),
                
                TextColumn::make('city')
                    ->label('Город')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
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
            ])
            ->filters([
                SelectFilter::make('city')
                    ->label('Город')
                    ->options(fn () => Supplier::distinct()->pluck('city', 'city')->filter()->toArray())
                    ->multiple(),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
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