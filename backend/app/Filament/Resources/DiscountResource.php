<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountResource\Pages;
use App\Models\Discount;
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;

class DiscountResource extends Resource
{
    use HasNavigationBadge;
    private static array $dataCache = [];
    protected static ?string $model = Discount::class;
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationGroup = 'Маркетинг';
    protected static ?string $navigationLabel = 'Персональные скидки';

    protected static ?string $modelLabel = 'Скидка';

    protected static ?string $pluralModelLabel = 'Скидки';

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
                                    ->label('Название скидки')
                                    ->required()
                                    ->maxLength(255),
                                
                                Select::make('type')
                                    ->label('Тип скидки')
                                    ->options([
                                        'personal' => 'Персональная',
                                        'first_order' => 'Первый заказ',
                                        'loyalty' => 'Программа лояльности',
                                        'referral' => 'Реферальная',
                                    ])
                                    ->required()
                                    ->default('personal'),
                                
                                TextInput::make('value')
                                    ->label('Размер скидки')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->suffix('%'),
                                
                                Toggle::make('is_global')
                                    ->label('Глобальная скидка')
                                    ->helperText('Применяется ко всем товарам')
                                    ->reactive()
                                    ->default(false),
                                
                                Toggle::make('is_active')
                                    ->label('Активна')
                                    ->default(true),
                            ]),
                    ]),

                Section::make('Период действия')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('start_at')
                                    ->label('Дата начала')
                                    ->nullable()
                                    ->native(false)
                                    ->displayFormat('d.m.Y H:i'),
                                
                                DateTimePicker::make('end_at')
                                    ->label('Дата окончания')
                                    ->nullable()
                                    ->native(false)
                                    ->displayFormat('d.m.Y H:i')
                                    ->after('start_at'),
                            ]),
                    ]),

                Section::make('Товары')
                    ->schema([
                        Select::make('products')
                            ->label('Товары, на которые действует скидка')
                            ->multiple()
                            ->relationship('products', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(100)
                            ->visible(fn ($get) => !$get('is_global'))
                            ->helperText('Оставьте пустым для глобальной скидки')
                            ->columnSpanFull(),
                        
                        Forms\Components\Placeholder::make('products_count')
                            ->label('Товаров со скидкой')
                            ->content(fn ($record) => $record ? $record->products()->count() : 0)
                            ->visible(fn ($record) => $record && !$record->is_global),
                    ]),

                Section::make('Пользователи')
                    ->schema([
                        Select::make('users')
                            ->label('Пользователи, имеющие скидку')
                            ->multiple()
                            ->relationship('users', 'email')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(100)
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' (' . $record->email . ')')
                            ->columnSpanFull(),
                        
                        Forms\Components\Placeholder::make('users_count')
                            ->label('Пользователей со скидкой')
                            ->content(fn ($record) => $record ? $record->users()->count() : 0)
                            ->visible(fn ($record) => $record !== null),
                    ]),

                Section::make('Дополнительные условия')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_order_amount')
                                    ->label('Минимальная сумма заказа')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(100)
                                    ->prefix('₽')
                                    ->nullable(),
                                
                                TextInput::make('usage_limit')
                                    ->label('Лимит использований')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->helperText('Сколько раз можно использовать'),
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
                    ->toggleable(),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'personal' => 'Персональная',
                        'first_order' => 'Первый заказ',
                        'loyalty' => 'Лояльность',
                        'referral' => 'Реферальная',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'personal' => 'primary',
                        'first_order' => 'success',
                        'loyalty' => 'warning',
                        'referral' => 'info',
                        default => 'gray',
                    }),
                
                TextColumn::make('value')
                    ->label('Размер')
                    ->suffix('%')
                    ->sortable()
                    ->alignCenter(),
                
                IconColumn::make('is_global')
                    ->label('Глобальная')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-cube')
                    ->trueColor('success')
                    ->falseColor('gray'),
                
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                TextColumn::make('users_count')
                    ->label('Пользователей')
                    ->counts('users')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->counts('products')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('start_at')
                    ->label('С')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('end_at')
                    ->label('По')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'personal' => 'Персональная',
                        'first_order' => 'Первый заказ',
                        'loyalty' => 'Лояльность',
                        'referral' => 'Реферальная',
                    ]),
                
                Filter::make('is_global')
                    ->label('Глобальные')
                    ->query(fn (Builder $query): Builder => $query->where('is_global', true)),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                
                Filter::make('active_period')
                    ->label('Действующие')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereNull('start_at')->orWhere('start_at', '<=', now());
                    })->where(function ($q) {
                        $q->whereNull('end_at')->orWhere('end_at', '>=', now());
                    })),
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
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListDiscounts::route('/'),
            'create' => Pages\CreateDiscount::route('/create'),
            'edit' => Pages\EditDiscount::route('/{record}/edit'),
            'view' => Pages\ViewDiscount::route('/{record}'),
        ];
    }

}