<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Sub_Subcategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;
use Filament\Notifications\Notification;

class ProductResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Управление товарами';
    protected static ?string $navigationLabel = 'Товары';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'brand:id,name',
                'images',
                'sub_subcategories.subcategory.category'
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Товар')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Основная информация')
                            ->schema([
                                Forms\Components\Section::make('Основная информация')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('sku')
                                                    ->label('Артикул')
                                                    ->maxLength(100)
                                                    ->unique(ignoreRecord: true)
                                                    ->helperText('Уникальный идентификатор товара'),
                                                
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Название товара')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(ignoreRecord: true),
                                                
                                                Forms\Components\Select::make('brand_id')
                                                    ->label('Бренд')
                                                    ->relationship('brand', 'name')
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->createOptionForm([
                                                        Forms\Components\TextInput::make('name')
                                                            ->label('Название бренда')
                                                            ->required(),
                                                        Forms\Components\TextInput::make('country')
                                                            ->label('Страна'),
                                                    ]),
                                                
                                                Forms\Components\TextInput::make('price')
                                                    ->label('Цена за базовое количество')
                                                    ->required()
                                                    ->numeric()
                                                    ->prefix('₽')
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->helperText('Для развесного товара это, например, цена за 100 г'),

                                                Forms\Components\Select::make('stock_unit')
                                                    ->label('Единица учёта')
                                                    ->options([
                                                        Product::STOCK_UNIT_PIECE => 'Штуки',
                                                        Product::STOCK_UNIT_GRAM => 'Граммы',
                                                    ])
                                                    ->default(Product::STOCK_UNIT_PIECE)
                                                    ->required()
                                                    ->live(),

                                                Forms\Components\TextInput::make('sale_step')
                                                    ->label('Шаг продажи')
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->default(1)
                                                    ->required()
                                                    ->suffix(fn ($get) => $get('stock_unit') === Product::STOCK_UNIT_GRAM ? 'г' : 'шт.')
                                                    ->helperText('Количество в корзине должно быть кратно этому шагу'),

                                                Forms\Components\TextInput::make('price_unit_quantity')
                                                    ->label('Базовое количество для цены')
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->default(1)
                                                    ->required()
                                                    ->suffix(fn ($get) => $get('stock_unit') === Product::STOCK_UNIT_GRAM ? 'г' : 'шт.')
                                                    ->helperText('Укажите 100, если price — цена за 100 г'),
                                                
                                                Forms\Components\TextInput::make('weight_grams')
                                                    ->label('Физический вес одной штуки')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('г')
                                                    ->visible(fn ($get) => $get('stock_unit') !== Product::STOCK_UNIT_GRAM),
                                                
                                                Forms\Components\Toggle::make('is_available')
                                                    ->label('Доступен для заказа')
                                                    ->default(true),
                                            ]),
                                    ]),
                                
                                Forms\Components\Section::make('Описание')
                                    ->schema([
                                        Forms\Components\RichEditor::make('description')
                                            ->label('Описание')
                                            ->columnSpanFull(),
                                        
                                        Forms\Components\Textarea::make('ingredients')
                                            ->label('Ингредиенты/Состав')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Скидки и акции')
                            ->schema([
                                Forms\Components\Section::make('Применяемые скидки')
                                    ->schema([
                                        Forms\Components\Select::make('discounts')
                                            ->label('Скидки на этот товар')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->relationship('discounts', 'name', function ($query) {
                                                $query->where('is_active', true)
                                                    ->where(function($q) {
                                                        $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                                                    })
                                                    ->where(function($q) {
                                                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                                                    });
                                            })
                                            ->helperText('Выберите скидки, которые будут применяться к этому товару')
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Акции на этом товаре')
                                    ->schema([
                                        Forms\Components\Placeholder::make('active_promotions')
                                            ->label('')
                                            ->content(function ($record) {
                                                if (!$record) return 'Сохраните товар, чтобы увидеть активные акции';

                                                $promotions = $record->discounts()
                                                    ->where('type', 'promotion')
                                                    ->where('is_active', true)
                                                    ->get();

                                                if ($promotions->isEmpty()) {
                                                    return 'Нет активных акций для этого товара';
                                                }

                                                return view('filament.components.active-discounts', [
                                                    'discounts' => $promotions
                                                ]);
                                            }),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Подарки')
                            ->schema([
                                Forms\Components\Section::make('Продажа готового подарка')
                                    ->schema([
                                        Forms\Components\Select::make('product_type')
                                            ->label('Тип товара')
                                            ->options([
                                                Product::TYPE_REGULAR => 'Обычный товар',
                                                Product::TYPE_PREASSEMBLED_GIFT => 'Собранный подарочный набор',
                                            ])
                                            ->default(Product::TYPE_REGULAR)
                                            ->required()
                                            ->live(),
                                        Forms\Components\Toggle::make('is_individual_sale_enabled')
                                            ->label('Можно заказывать отдельно')
                                            ->default(true)
                                            ->helperText('Отключите для товара, доступного только внутри конструктора'),
                                        Forms\Components\Textarea::make('assembly_instructions')
                                            ->label('Внутренняя инструкция по сборке')
                                            ->rows(5)
                                            ->columnSpanFull()
                                            ->visible(fn ($get) => $get('product_type') === Product::TYPE_PREASSEMBLED_GIFT)
                                            ->helperText('Покупателю не показывается; доступна администратору и сборщику.'),
                                    ])->columns(2),
                                Forms\Components\Section::make('Форматы для конструктора')
                                    ->description('Наличие активной записи разрешает товар в конструкторе.')
                                    ->schema([
                                        Forms\Components\Repeater::make('constructorSizes')
                                            ->relationship()
                                            ->schema([
                                                Forms\Components\TextInput::make('label')
                                                    ->label('Название формата')
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('product_quantity')
                                                    ->label('Количество товара')
                                                    ->integer()
                                                    ->minValue(1)
                                                    ->required(),
                                                Forms\Components\Select::make('gift_size_profile_id')
                                                    ->label('Профиль размера')
                                                    ->relationship(
                                                        'sizeProfile',
                                                        'name',
                                                        fn ($query) => $query->where('kind', 'item')
                                                    )
                                                    ->required()
                                                    ->searchable()
                                                    ->preload(),
                                                Forms\Components\Select::make('constructor_role')
                                                    ->label('Роль в простом конструкторе')
                                                    ->options([
                                                        'tea' => 'Чай',
                                                        'sweet' => 'Сладость',
                                                        'general' => 'Обычный компонент',
                                                    ])
                                                    ->default('general')
                                                    ->required(),
                                                Forms\Components\Toggle::make('is_active')
                                                    ->label('Активен')
                                                    ->default(true),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Категория')
                            ->schema([
                                Forms\Components\Section::make('Категория')
                                    ->schema([
                                        Forms\Components\Select::make('sub_subcategories')
                                            ->label('Под-подкатегория')
                                            ->relationship('sub_subcategories', 'name')
                                            ->multiple(false)
                                            ->options(function () {
                                                return Sub_Subcategory::with(['subcategory.category'])
                                                    ->get()
                                                    ->mapWithKeys(function ($item) {
                                                        $path = ($item->subcategory?->category?->name ?? '—') . ' → ' . 
                                                                ($item->subcategory?->name ?? '—') . ' → ' . 
                                                                $item->name;
                                                        return [$item->id => $path];
                                                    });
                                            })
                                            ->searchable()
                                            ->required()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Изображения')
                            ->schema([
                                Forms\Components\Section::make('Изображения')
                                    ->schema([
                                        Forms\Components\Repeater::make('images')
                                            ->relationship()
                                            ->schema([
                                                Forms\Components\Grid::make(4)
                                                    ->schema([
                                                        Forms\Components\FileUpload::make('path')
                                                            ->label('Изображение')
                                                            ->image()
                                                            ->directory(function (callable $get, $record) {
                                                                if ($record) {
                                                                    return 'products/' . $record->product_id;
                                                                }
                                                                return 'products/temp';
                                                            })
                                                            ->visibility('public')
                                                            ->maxSize(2048)
                                                            ->columnSpan(2)
                                                            ->required(),
                                                        
                                                        Forms\Components\TextInput::make('sort_order')
                                                            ->label('Порядок')
                                                            ->numeric()
                                                            ->default(0)
                                                            ->columnSpan(1),
                                                        
                                                        Forms\Components\Toggle::make('is_main')
                                                            ->label('Главное')
                                                            ->columnSpan(1),
                                                        
                                                        Forms\Components\Toggle::make('is_background')
                                                            ->label('Фоновое')
                                                            ->columnSpan(1),
                                                        
                                                        Forms\Components\TextInput::make('alt')
                                                            ->label('Alt текст')
                                                            ->columnSpan(2),
                                                        
                                                        Forms\Components\TextInput::make('title')
                                                            ->label('Title')
                                                            ->columnSpan(2),
                                                    ]),
                                            ])
                                            ->defaultItems(0)
                                            ->collapsible()
                                            ->columnSpanFull(),
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
                ImageColumn::make('main_image')
                    ->label('Фото')
                    ->circular()
                    ->getStateUsing(function ($record) {
                        $mainImage = $record->images->firstWhere('is_main', true);
                        return $mainImage ? $mainImage->image_url : null;
                    })
                    ->defaultImageUrl(url('/images/default-product.jpg')),
                
                TextColumn::make('sku')
                    ->label('Артикул')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(30),
                
                TextColumn::make('brand.name')
                    ->label('Бренд')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('category')
                    ->label('Категория')
                    ->getStateUsing(function ($record) {
                        $subSub = $record->sub_subcategories->first();
                        if (!$subSub) return '—';
                        
                        $category = $subSub->subcategory?->category?->name ?? '';
                        $subcategory = $subSub->subcategory?->name ?? '';
                        $subSubName = $subSub->name ?? '';
                        
                        return trim("{$category} → {$subcategory} → {$subSubName}", ' →');
                    })
                    ->toggleable(),
                
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->description(fn ($record) => 'за ' . $record->priceUnitQuantity()
                        . ($record->isWeighted() ? ' г' : ' шт.'))
                    ->sortable(),
                
                TextColumn::make('total_quantity')
                    ->label('Остаток')
                    ->getStateUsing(fn ($record) => $record->total_quantity)
                    ->formatStateUsing(fn ($state, $record) => $state
                        . ($record->isWeighted() ? ' г' : ' шт.'))
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->badge()
                    ->alignCenter(),
                
                TextColumn::make('sold_count')
                    ->label('Продано')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('in_stock')
                    ->label('В наличии')
                    ->query(fn (Builder $query): Builder => $query->where('total_quantity', '>', 0)),
                
                Filter::make('out_of_stock')
                    ->label('Нет в наличии')
                    ->query(fn (Builder $query): Builder => $query->where('total_quantity', '<=', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->orders()->exists()) {
                            Notification::make()
                                ->title('Невозможно удалить товар')
                                ->body('Этот товар есть в заказах. Сначала удалите или переназначьте заказы.')
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
                                if ($record->orders()->exists()) {
                                    Notification::make()
                                        ->title('Невозможно удалить некоторые товары')
                                        ->body('Товары, которые есть в заказах, не могут быть удалены.')
                                        ->danger()
                                        ->send();

                                    $this->halt();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'import' => Pages\ImportPreview::route('/import'),
            'import-progress' => Pages\ImportProgress::route('/import-progress/{batchId}'),
            'import-result' => Pages\ImportResult::route('/import-result/{batchId}'),
            'import-images' => Pages\ImportImages::route('/import-images'),
            'import-images-progress' => Pages\ImportImagesProgress::route('/import-images-progress/{batchId}'),
            'import-images-result' => Pages\ImportImagesResult::route('/import-images-result/{batchId}'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
