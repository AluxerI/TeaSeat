<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use App\Traits\HasNavigationBadge;

class OrderResource extends Resource
{
    use HasNavigationBadge;
    private static array $dataCache = []; 
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Управление продажами';
    protected static ?string $navigationLabel = 'Заказы';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Информация о заказе')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->label('Номер заказа')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                Forms\Components\Select::make('status')
                                    ->label('Статус')
                                    ->options([
                                        Order::STATUS_CART => 'Корзина',
                                        Order::STATUS_PENDING => 'Ожидает подтверждения',
                                        Order::STATUS_CONFIRMED => 'Подтвержден',
                                        Order::STATUS_PROCESSING => 'В обработке',
                                        Order::STATUS_SHIPPED => 'Отправлен',
                                        Order::STATUS_DELIVERED => 'Доставлен',
                                        Order::STATUS_CANCELLED => 'Отменен',
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, $record) {
                                        if ($record && $state !== $record->status) {
                                            $set('status_changed', true);
                                        }
                                    }),
                                
                                Forms\Components\Checkbox::make('is_supplier_order')
                                    ->label('Заказ у поставщика')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                        
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Дата создания')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Внутренние заметки')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Клиент')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Пользователь')
                                    ->relationship('user', 'email')
                                    ->searchable()
                                    ->preload()
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                Forms\Components\Select::make('shipping_address_id')
                                    ->label('Адрес доставки')
                                    ->relationship('shippingAddress', 'street', function ($query, $get) {
                                        if ($get('user_id')) {
                                            $query->where('user_id', $get('user_id'));
                                        }
                                    })
                                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                                        $record->getFullAddress()
                                    )
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),

                Section::make('Доставка и оплата')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('delivery_method_id')
                                    ->label('Способ доставки')
                                    ->relationship('deliveryMethod', 'name')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                Forms\Components\TextInput::make('shipping_cost')
                                    ->label('Стоимость доставки')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                Forms\Components\TextInput::make('tracking_number')
                                    ->label('Трек-номер')
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make('Товары в заказе')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        Forms\Components\TextInput::make('product.name')
                                            ->label('Товар')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(2),
                                        
                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Кол-во')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Цена')
                                            ->numeric()
                                            ->prefix('₽')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('final_unit_price')
                                            ->label('Цена со скидкой')
                                            ->numeric()
                                            ->prefix('₽')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('total_price')
                                            ->label('Сумма')
                                            ->numeric()
                                            ->prefix('₽')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('Итоги')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('products_total')
                                    ->label('Товары')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                Forms\Components\TextInput::make('promotion_discount')
                                    ->label('Скидка по акции')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                Forms\Components\TextInput::make('personal_discount')
                                    ->label('Перс. скидка')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                Forms\Components\TextInput::make('final_total')
                                    ->label('Итого')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->extraAttributes(['class' => 'font-bold text-lg']),
                            ]),
                    ]),

                Section::make('История статусов')
                    ->schema([
                        Forms\Components\Placeholder::make('status_history')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return null;
                                
                                $history = OrderStatusHistory::where('order_id', $record->id)
                                    ->with('changer')
                                    ->orderBy('created_at', 'desc')
                                    ->get();
                                
                                if ($history->isEmpty()) return 'Нет истории';
                                
                                $html = '<div class="space-y-2">';
                                foreach ($history as $entry) {
                                    $html .= '<div class="text-sm border-b pb-2">';
                                    $html .= '<span class="font-medium">' . $entry->created_at->format('d.m.Y H:i') . '</span> - ';
                                    $html .= '<span class="text-gray-600">' . Order::getStatusName($entry->from_status) . '</span>';
                                    $html .= ' → ';
                                    $html .= '<span class="text-gray-900 font-medium">' . Order::getStatusName($entry->to_status) . '</span>';
                                    if ($entry->changer) {
                                        $html .= ' <span class="text-xs text-gray-500">(изменено: ' . $entry->changer->name . ')</span>';
                                    }
                                    if ($entry->notes) {
                                        $html .= '<div class="text-xs text-gray-500 mt-1">' . $entry->notes . '</div>';
                                    }
                                    $html .= '</div>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ])
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('№ заказа')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Клиент')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::getStatusName($state))
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_CART => 'gray',
                        Order::STATUS_PENDING => 'warning',
                        Order::STATUS_CONFIRMED => 'info',
                        Order::STATUS_PROCESSING => 'primary',
                        Order::STATUS_SHIPPED => 'purple',
                        Order::STATUS_DELIVERED => 'success',
                        Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Товаров')
                    ->counts('items')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('final_total')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('is_supplier_order')
                    ->label('Поставщик')
                    ->boolean()
                    ->trueIcon('heroicon-o-truck')
                    ->falseIcon('heroicon-o-building-storefront'),
                
                Tables\Columns\TextColumn::make('deliveryMethod.name')
                    ->label('Доставка')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        Order::STATUS_CART => 'Корзина',
                        Order::STATUS_PENDING => 'Ожидает подтверждения',
                        Order::STATUS_CONFIRMED => 'Подтвержден',
                        Order::STATUS_PROCESSING => 'В обработке',
                        Order::STATUS_SHIPPED => 'Отправлен',
                        Order::STATUS_DELIVERED => 'Доставлен',
                        Order::STATUS_CANCELLED => 'Отменен',
                    ]),
                
                Filter::make('is_supplier_order')
                    ->label('Заказы у поставщиков')
                    ->query(fn (Builder $query): Builder => $query->where('is_supplier_order', true)),
                
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('С даты'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('По дату'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    
                    Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\Action::make('confirm')
                        ->label('Подтвердить')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Order $record) {
                            $record->update(['status' => Order::STATUS_CONFIRMED]);
                            Notification::make()
                                ->title('Заказ подтвержден')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Order $record): bool => 
                            $record->status === Order::STATUS_PENDING
                        ),
                    
                    Tables\Actions\Action::make('ship')
                        ->label('Отправить')
                        ->icon('heroicon-o-truck')
                        ->color('purple')
                        ->form([
                            Forms\Components\TextInput::make('tracking_number')
                                ->label('Трек-номер')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->action(function (Order $record, array $data) {
                            $record->update([
                                'status' => Order::STATUS_SHIPPED,
                                'tracking_number' => $data['tracking_number']
                            ]);
                            Notification::make()
                                ->title('Заказ отправлен')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Order $record): bool => 
                            in_array($record->status, [Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING])
                        ),
                    
                    Tables\Actions\Action::make('deliver')
                        ->label('Доставлен')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Order $record) {
                            $record->update(['status' => Order::STATUS_DELIVERED]);
                            Notification::make()
                                ->title('Заказ доставлен')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Order $record): bool => 
                            $record->status === Order::STATUS_SHIPPED
                        ),
                    
                    Tables\Actions\Action::make('cancel')
                        ->label('Отменить')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label('Причина отмены')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Order $record, array $data) {
                            $record->update([
                                'status' => Order::STATUS_CANCELLED,
                                'internal_notes' => ($record->internal_notes ?? '') . 
                                    "\nОтменен: " . $data['cancellation_reason']
                            ]);
                            Notification::make()
                                ->title('Заказ отменен')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Order $record): bool => 
                            !in_array($record->status, [
                                Order::STATUS_DELIVERED, 
                                Order::STATUS_CANCELLED
                            ])
                        ),
                ]),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

}