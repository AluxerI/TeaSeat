<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Discount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;
use Filament\Notifications\Notification;

class CouponResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Discount::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Маркетинг';
    protected static ?string $navigationLabel = 'Промокоды';
    protected static ?string $modelLabel = 'Промокод';
    protected static ?string $pluralModelLabel = 'Промокоды';
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Фильтр: только промокоды (cart и shipping)
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type', [Discount::TYPE_CART, Discount::TYPE_SHIPPING])
            ->whereNotNull('code')
            ->orderBy('created_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Промокод')
                    ->tabs([
                        Tab::make('Основная информация')
                            ->schema([
                                Section::make('Основная информация')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Название промокода')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(ignoreRecord: true),
                                                
                                                TextInput::make('code')
                                                    ->label('Промокод')
                                                    ->required()
                                                    ->maxLength(50)
                                                    ->unique(ignoreRecord: true, ignorable: fn ($record) => $record)
                                                    ->helperText('Уникальный код, который будет вводить пользователь')
                                                    ->prefix('→')
                                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                                
                                                Forms\Components\Hidden::make('type')
                                                    ->default(Discount::TYPE_CART),
                                                
                                                TextInput::make('value')
                                                    ->label('Процент скидки')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->step(0.01)
                                                    ->suffix('%'),
                                                
                                                Toggle::make('is_active')
                                                    ->label('Активен')
                                                    ->default(true)
                                                    ->helperText('Если выключить — промокод перестанет работать'),
                                            ]),
                                        
                                        Textarea::make('description')
                                            ->label('Описание')
                                            ->rows(2)
                                            ->columnSpanFull()
                                            ->helperText('Внутреннее описание, пользователи его не видят'),
                                    ]),

                                Section::make('Период действия')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                DateTimePicker::make('start_date')
                                                    ->label('Дата начала')
                                                    ->nullable()
                                                    ->native(false)
                                                    ->displayFormat('d.m.Y H:i')
                                                    ->helperText('Оставьте пустым для мгновенного старта'),
                                                
                                                DateTimePicker::make('end_date')
                                                    ->label('Дата окончания')
                                                    ->nullable()
                                                    ->native(false)
                                                    ->displayFormat('d.m.Y H:i')
                                                    ->after('start_date')
                                                    ->helperText('Оставьте пустым для бессрочного действия'),
                                            ]),
                                    ]),

                                Section::make('Условия применения')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('min_order_amount')
                                                    ->label('Минимальная сумма заказа')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(100)
                                                    ->prefix('₽')
                                                    ->nullable()
                                                    ->helperText('Оставьте пустым — без ограничений'),
                                                
                                                TextInput::make('usage_limit')
                                                    ->label('Общий лимит использований')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->nullable()
                                                    ->helperText('0 или пусто — без лимита'),
                                                
                                                TextInput::make('usage_per_user')
                                                    ->label('Лимит на пользователя')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->nullable()
                                                    ->helperText('Сколько раз один пользователь может применить промокод'),
                                                
                                                TextInput::make('used_count')
                                                    ->label('Использовано раз')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->helperText('Автоматически обновляется при использовании'),
                                            ]),
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
                    ->toggleable(),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('code')
                    ->label('Промокод')
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->copyable()
                    ->copyMessage('Промокод скопирован')
                    ->icon('heroicon-m-clipboard'),
                
                TextColumn::make('value')
                    ->label('Скидка')
                    ->suffix('%')
                    ->sortable()
                    ->alignCenter()
                    ->color('success')
                    ->weight('bold'),
                
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cart' => 'На корзину',
                        'shipping' => 'На доставку',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cart' => 'info',
                        'shipping' => 'primary',
                        default => 'gray',
                    }),
                
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                TextColumn::make('min_order_amount')
                    ->label('Мин. сумма')
                    ->money('RUB')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                
                TextColumn::make('usage_limit')
                    ->label('Лимит')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('∞'),
                
                TextColumn::make('used_count')
                    ->label('Использовано')
                    ->sortable()
                    ->color(fn ($state, $record) => 
                        $record->usage_limit && $state >= $record->usage_limit 
                            ? 'danger' 
                            : 'gray'
                    )
                    ->toggleable(),
                
                TextColumn::make('start_date')
                    ->label('С')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                
                TextColumn::make('end_date')
                    ->label('По')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('∞')
                    ->color(fn ($state) => 
                        $state && $state->isPast() ? 'danger' : 'gray'
                    ),
                
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'cart' => 'На корзину',
                        'shipping' => 'На доставку',
                    ]),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                
                Filter::make('active_period')
                    ->label('Действующие сейчас')
                    ->query(fn (Builder $query): Builder => $query
                        ->where(function ($q) {
                            $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                        })
                    ),
                
                Filter::make('expired')
                    ->label('Просроченные')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('end_date', '<', now())
                        ->where('is_active', true)
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->used_count > 0) {
                            Notification::make()
                                ->title('Невозможно удалить промокод')
                                ->body('Промокод уже использован. Лучше деактивируйте его.')
                                ->danger()
                                ->send();

                            $this->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->used_count > 0) {
                                    Notification::make()
                                        ->title('Невозможно удалить некоторые промокоды')
                                        ->body('Промокоды, которые уже использовались, нельзя удалить.')
                                        ->danger()
                                        ->send();

                                    $this->halt();
                                }
                            }
                        }),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Активировать')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->color('success')
                        ->icon('heroicon-o-check-circle'),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Деактивировать')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->color('danger')
                        ->icon('heroicon-o-x-circle'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
            'view' => Pages\ViewCoupon::route('/{record}'),
        ];
    }
}