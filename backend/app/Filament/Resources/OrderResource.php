<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\AddressClient;
use App\Models\Inventory;
use App\Models\Discount;
use App\Services\PricingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use App\Traits\HasNavigationBadge;
use Illuminate\Support\Facades\Auth;

class OrderResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Управление продажами';
    protected static ?string $navigationLabel = 'Заказы';
    protected static ?string $recordTitleAttribute = 'id';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'items.product', 'shippingAddress', 'deliveryMethod'])
            ->where('status', '!=', Order::STATUS_CART);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Информация о заказе')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Placeholder::make('order_number')
                                    ->label('Номер заказа')
                                    ->content(fn ($record) => $record ? 'TE-' . str_pad($record->id, 6, '0', STR_PAD_LEFT) : 'Новый заказ'),
                                
                                Select::make('status')
                                    ->label('Статус')
                                    ->options([
                                        Order::STATUS_PENDING => 'Ожидает подтверждения',
                                        Order::STATUS_CONFIRMED => 'Подтвержден',
                                        Order::STATUS_PROCESSING => 'В обработке',
                                        Order::STATUS_SHIPPED => 'Отправлен',
                                        Order::STATUS_DELIVERED => 'Доставлен',
                                        Order::STATUS_CANCELLED => 'Отменен',
                                    ])
                                    ->default(Order::STATUS_PENDING)
                                    ->required()
                                    ->reactive(),
                                
                                Placeholder::make('created_at')
                                    ->label('Дата создания')
                                    ->content(fn ($record) => $record?->created_at?->format('d.m.Y H:i') ?? '—')
                                    ->visible(fn ($record) => $record !== null),
                            ]),
                        
                        Textarea::make('customer_notes')
                            ->label('Заметки клиента')
                            ->rows(2)
                            ->columnSpanFull(),
                        
                        Textarea::make('internal_notes')
                            ->label('Внутренние заметки')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Клиент')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Пользователь')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $user = User::find($state);
                                        if ($user) {
                                            $set('contact_name', $user->name);
                                            $set('contact_email', $user->email);
                                            $set('contact_phone', $user->phone);
                                        }
                                    }),
                                
                                TextInput::make('contact_name')
                                    ->label('Контактное лицо')
                                    ->required()
                                    ->maxLength(255),
                                
                                TextInput::make('contact_phone')
                                    ->label('Телефон')
                                    ->tel()
                                    ->maxLength(20)
                                    ->required(),
                                
                                TextInput::make('contact_email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->required(),
                            ]),
                    ]),

                Section::make('Адрес доставки')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('shipping_address_id')
                                    ->label('Адрес доставки')
                                    ->options(function (callable $get) {
                                        $userId = $get('user_id');
                                        if (!$userId) return [];
                                        
                                        return AddressClient::where('user_id', $userId)
                                            ->get()
                                            ->mapWithKeys(function ($address) {
                                                return [$address->id => $address->getFullAddress()];
                                            });
                                    })
                                    ->searchable()
                                    ->required()
                                    ->createOptionForm([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('postal_code')
                                                    ->label('Индекс')
                                                    ->maxLength(10),
                                                TextInput::make('city')
                                                    ->label('Город')
                                                    ->required()
                                                    ->maxLength(255),
                                                TextInput::make('street')
                                                    ->label('Улица, дом, квартира')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->createOptionUsing(function (array $data, callable $get) {
                                        $userId = $get('user_id');
                                        if (!$userId) return null;
                                        
                                        $data['user_id'] = $userId;
                                        return AddressClient::create($data);
                                    }),
                            ]),
                    ]),

                Section::make('Доставка и оплата')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('delivery_method_id')
                                    ->label('Способ доставки')
                                    ->relationship('deliveryMethod', 'name')
                                    ->required(),
                                
                                TextInput::make('shipping_cost')
                                    ->label('Стоимость доставки')
                                    ->numeric()
                                    ->prefix('₽')
                                    ->default(0)
                                    ->required()
                                    ->reactive(),
                                
                                TextInput::make('tracking_number')
                                    ->label('Трек-номер')
                                    ->maxLength(255)
                                    ->default(fn () => 'TRACK-' . strtoupper(uniqid())),
                                
                                Select::make('payment_method')
                                    ->label('Способ оплаты')
                                    ->options([
                                        'cash' => 'Наличные',
                                        'card' => 'Карта при получении',
                                        'online' => 'Онлайн',
                                    ])
                                    ->required(),
                            ]),
                    ]),

                Section::make('Товары в заказе')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(12)
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Товар')
                                            ->options(function () {
                                                return Product::select('id', 'name')
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->required()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get, $livewire) {
                                                $product = Product::find($state);
                                                $user = User::find($get('../../user_id'));
                                                
                                                if ($product) {
                                                    $quantity = max(
                                                        $product->saleStep(),
                                                        (int) ($get('quantity') ?? $product->saleStep())
                                                    );
                                                    if ($quantity % $product->saleStep() !== 0) {
                                                        $quantity = $product->saleStep();
                                                    }
                                                    $line = app(PricingService::class)->quoteProductLine(
                                                        $product,
                                                        $quantity,
                                                        $user
                                                    );

                                                    $set('quantity', $quantity);
                                                    $set('unit_price', $product->price);
                                                    $set('stock_unit', $product->stockUnit());
                                                    $set('sale_step', $product->saleStep());
                                                    $set('price_unit_quantity', $product->priceUnitQuantity());
                                                    $set('final_unit_price', $line['final_unit_price']);
                                                    $promotion = $line['promotion'] ?? null;
                                                    $set('promotion_discount_id', $promotion['id'] ?? null);
                                                    $set(
                                                        'promotion_discount_percent',
                                                        ($promotion['value_type'] ?? null) === Discount::VALUE_PERCENT
                                                            ? $promotion['value']
                                                            : 0
                                                    );
                                                    $set('personal_discount_percent', 0);
                                                    $set(
                                                        'promotion_discount_amount',
                                                        $line['promotion_discount_amount']
                                                    );
                                                    $set('selected_discount_id', null);
                                                    $set('selected_discount_amount', 0);
                                                    
                                                    // Информация о скидках для отображения
                                                    $set('_promotion_discount', $line['total_discount_percent']);
                                                    $set('_personal_discount', 0);
                                                    
                                                    // Получаем общий остаток на складах
                                                    $totalStock = Inventory::where('product_id', $state)->sum('quantity');
                                                    $set('_stock_info', $totalStock);
                                                }
                                                self::calculateTotals($get, $set);
                                            })
                                            ->columnSpan(4),
                                        
                                        TextInput::make('quantity')
                                            ->label('Кол-во')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->step(function ($get) {
                                                return Product::find($get('product_id'))?->saleStep() ?? 1;
                                            })
                                            ->rules([
                                                fn ($get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                                                    $product = Product::find($get('product_id'));
                                                    if (!$product) {
                                                        return;
                                                    }

                                                    try {
                                                        $product->assertValidSaleQuantity((int) $value);
                                                    } catch (\DomainException $exception) {
                                                        $fail($exception->getMessage());
                                                    }
                                                },
                                            ])
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $get, callable $set) {
                                                $product = Product::find($get('product_id'));
                                                if (!$product) {
                                                    return;
                                                }

                                                $quantity = (int) ($get('quantity') ?? 0);
                                                try {
                                                    $product->assertValidSaleQuantity($quantity);
                                                } catch (\DomainException) {
                                                    return;
                                                }

                                                $line = app(PricingService::class)
                                                    ->quoteProductLine(
                                                        $product,
                                                        $quantity,
                                                        User::find($get('../../user_id'))
                                                    );
                                                $set('final_unit_price', $line['final_unit_price']);
                                                $set('promotion_discount_amount', $line['promotion_discount_amount']);
                                                self::calculateTotals($get, $set);
                                            })
                                            ->columnSpan(2),
                                        
                                        TextInput::make('unit_price')
                                            ->label('Цена')
                                            ->numeric()
                                            ->prefix('₽')
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),
                                        
                                        Placeholder::make('discount_info')
                                            ->label('Скидки')
                                            ->content(function ($get) {
                                                $promo = $get('_promotion_discount') ?? 0;
                                                $personal = $get('_personal_discount') ?? 0;
                                                
                                                if ($promo == 0 && $personal == 0) {
                                                    return '—';
                                                }
                                                
                                                $parts = [];
                                                if ($promo > 0) $parts[] = "Акция: {$promo}%";
                                                if ($personal > 0) $parts[] = "Перс: {$personal}%";
                                                
                                                return implode(' + ', $parts);
                                            })
                                            ->columnSpan(1),
                                        
                                        TextInput::make('final_unit_price')
                                            ->label('Цена со скидкой')
                                            ->numeric()
                                            ->prefix('₽')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),
                                        
                                        Placeholder::make('stock_info')
                                            ->label('Остаток')
                                            ->content(function ($get) {
                                                $stock = $get('_stock_info');
                                                if ($stock === null) return '—';
                                                return $stock . ($get('stock_unit') === Product::STOCK_UNIT_GRAM ? ' г' : ' шт.');
                                            })
                                            ->columnSpan(1),
                                        
                                        Placeholder::make('total')
                                            ->label('Сумма')
                                            ->content(function ($get) {
                                                return number_format(self::lineTotalFromState([
                                                    'quantity' => $get('quantity'),
                                                    'final_unit_price' => $get('final_unit_price'),
                                                    'price_unit_quantity' => $get('price_unit_quantity'),
                                                ]), 2) . ' ₽';
                                            })
                                            ->columnSpan(1),
                                    ]),

                                Hidden::make('promotion_discount_id'),
                                Hidden::make('selected_discount_id'),
                                Hidden::make('stock_unit')->default(Product::STOCK_UNIT_PIECE),
                                Hidden::make('sale_step')->default(1),
                                Hidden::make('price_unit_quantity')->default(1),
                                Hidden::make('promotion_discount_percent')->default(0),
                                Hidden::make('personal_discount_percent')->default(0),
                                Hidden::make('promotion_discount_amount')->default(0),
                                Hidden::make('selected_discount_amount')->default(0),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string => 
                                isset($state['product_id']) 
                                    ? Product::find($state['product_id'])?->name 
                                    : 'Новый товар'
                            )
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['total_price'] = self::lineTotalFromState($data);
                                $data['pricing_snapshot'] = self::makeLinePricingSnapshot($data);
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $data['total_price'] = self::lineTotalFromState($data);
                                $data['pricing_snapshot'] = self::makeLinePricingSnapshot($data);
                                return $data;
                            })
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                self::calculateTotals($get, $set);
                            }),
                    ]),

                Section::make('Итоги')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Placeholder::make('products_total_display')
                                    ->label('Товары')
                                    ->content(function ($get) {
                                        $items = $get('items') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += self::lineTotalFromState($item, 'unit_price');
                                        }
                                        return number_format($total, 2) . ' ₽';
                                    }),
                                
                                Placeholder::make('shipping_cost_display')
                                    ->label('Доставка')
                                    ->content(fn ($get) => number_format($get('shipping_cost') ?? 0, 2) . ' ₽'),
                                
                                Placeholder::make('promotion_discount_display')
                                    ->label('Скидка по акции')
                                    ->content(fn ($get) => number_format(
                                        collect($get('items') ?? [])->sum('promotion_discount_amount'),
                                        2
                                    ) . ' ₽'),
                                
                                Placeholder::make('personal_discount_display')
                                    ->label('Перс. скидка')
                                    ->content(fn ($get) => number_format(
                                        collect($get('items') ?? [])->sum('selected_discount_amount'),
                                        2
                                    ) . ' ₽'),
                                
                                Placeholder::make('final_total_display')
                                    ->label('Итого')
                                    ->content(function ($get) {
                                        $items = $get('items') ?? [];
                                        $productsTotal = 0;
                                        foreach ($items as $item) {
                                            $productsTotal += self::lineTotalFromState($item);
                                        }
                                        
                                        $shipping = $get('shipping_cost') ?? 0;
                                        $cartDiscount = $get('cart_discount') ?? 0;
                                        $shippingDiscount = $get('shipping_discount') ?? 0;
                                        $final = max(0, $productsTotal - $cartDiscount)
                                            + max(0, $shipping - $shippingDiscount);
                                        
                                        return number_format(max(0, $final), 2) . ' ₽';
                                    })
                                    ->extraAttributes(['class' => 'font-bold text-lg']),
                                
                                Hidden::make('final_total')
                                    ->default(0)
                                    ->dehydrateStateUsing(function ($state, callable $get) {
                                        $items = $get('items') ?? [];
                                        $productsTotal = 0;
                                        foreach ($items as $item) {
                                            $productsTotal += self::lineTotalFromState($item);
                                        }
                                        
                                        $shipping = $get('shipping_cost') ?? 0;
                                        $cartDiscount = $get('cart_discount') ?? 0;
                                        $shippingDiscount = $get('shipping_discount') ?? 0;

                                        return max(0, $productsTotal - $cartDiscount)
                                            + max(0, $shipping - $shippingDiscount);
                                    }),

                                Hidden::make('products_total')
                                    ->dehydrateStateUsing(fn ($state, callable $get) => collect($get('items') ?? [])
                                        ->sum(fn (array $item) => self::lineTotalFromState($item, 'unit_price'))),
                                Hidden::make('promotion_discount')
                                    ->dehydrateStateUsing(fn ($state, callable $get) => collect($get('items') ?? [])
                                        ->sum('promotion_discount_amount')),
                                Hidden::make('personal_discount')
                                    ->dehydrateStateUsing(fn ($state, callable $get) => collect($get('items') ?? [])
                                        ->sum('selected_discount_amount')),
                                Hidden::make('cart_discount')->default(0),
                                Hidden::make('shipping_discount')->default(0),
                            ]),
                    ]),
            ]);
    }

    protected static function calculateTotals(callable $get, callable $set): void
    {
        // Триггер для пересчета - всё обновляется через Placeholder
    }

    private static function lineTotalFromState(
        array $data,
        string $priceField = 'final_unit_price'
    ): float {
        return round(
            (float) ($data[$priceField] ?? 0)
                * (int) ($data['quantity'] ?? 0)
                / max(1, (int) ($data['price_unit_quantity'] ?? 1)),
            2
        );
    }

    private static function makeLinePricingSnapshot(array $data): array
    {
        return [
            'product_id' => $data['product_id'] ?? null,
            'quantity' => (int) ($data['quantity'] ?? 0),
            'stock_unit' => $data['stock_unit'] ?? Product::STOCK_UNIT_PIECE,
            'sale_step' => (int) ($data['sale_step'] ?? 1),
            'price_unit_quantity' => (int) ($data['price_unit_quantity'] ?? 1),
            'unit_price' => (float) ($data['unit_price'] ?? 0),
            'base_total' => self::lineTotalFromState($data, 'unit_price'),
            'discount_uses' => ($data['stock_unit'] ?? Product::STOCK_UNIT_PIECE) === Product::STOCK_UNIT_GRAM
                ? 1
                : (int) ($data['quantity'] ?? 0),
            'promotion_discount_id' => $data['promotion_discount_id'] ?? null,
            'promotion_discount_amount' => (float) ($data['promotion_discount_amount'] ?? 0),
            'selected_discount_id' => $data['selected_discount_id'] ?? null,
            'selected_discount_amount' => (float) ($data['selected_discount_amount'] ?? 0),
            'final_unit_price' => (float) ($data['final_unit_price'] ?? 0),
            'final_total' => self::lineTotalFromState($data),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('№ заказа')
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->where('id', 'LIKE', "%{$search}%"))
                    ->sortable(),
                
                TextColumn::make('user.name')
                    ->label('Клиент')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('shippingAddress.city')
                    ->label('Город')
                    ->toggleable(),
                
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::getStatusName($state))
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'warning',
                        Order::STATUS_CONFIRMED => 'info',
                        Order::STATUS_PROCESSING => 'primary',
                        Order::STATUS_SHIPPED => 'purple',
                        Order::STATUS_DELIVERED => 'success',
                        Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                
                TextColumn::make('items_count')
                    ->label('Товаров')
                    ->counts('items')
                    ->sortable(),
                
                TextColumn::make('final_total')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        Order::STATUS_PENDING => 'Ожидает подтверждения',
                        Order::STATUS_CONFIRMED => 'Подтвержден',
                        Order::STATUS_PROCESSING => 'В обработке',
                        Order::STATUS_SHIPPED => 'Отправлен',
                        Order::STATUS_DELIVERED => 'Доставлен',
                        Order::STATUS_CANCELLED => 'Отменен',
                    ]),
            ])
            ->actions([
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
