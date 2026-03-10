<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Category; 
use App\Models\Subcategory; 
use App\Models\Sub_Subcategory;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;

class ProductResource extends Resource
{
    use HasNavigationBadge;
    
    private static array $dataCache = [];

    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Управление товарами';
    protected static ?string $navigationLabel = 'Товары';

    private static function getData($record): array
    {
        $id = $record->id;
        if (!isset(self::$dataCache[$id])) {
            self::$dataCache[$id] = $record->getAllData();
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
                                    ->label('Название товара')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('price')
                                    ->label('Цена')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₽')
                                    ->step(0.01)
                                    ->afterStateUpdated(fn ($record) => $record?->clearCache()),
                                
                                Forms\Components\Select::make('brand_id')
                                    ->label('Бренд')
                                    ->relationship('brand', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->afterStateUpdated(fn ($record) => $record?->clearCache()),
                                
                                Forms\Components\TextInput::make('weight_grams')
                                    ->label('Вес (грамм)')
                                    ->numeric()
                                    ->suffix('г'),
                                
                                Forms\Components\TextInput::make('sold_count')
                                    ->label('Продано')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                        
                        Forms\Components\RichEditor::make('description')
                            ->label('Описание')
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('ingredients')
                            ->label('Ингредиенты/Состав')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Категория')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('category_id')
                                    ->label('Категория')
                                    ->options(fn () => Category::pluck('name', 'id'))
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('subcategory_id', null)),
                                
                                Forms\Components\Select::make('subcategory_id')
                                    ->label('Подкатегория')
                                    ->options(function (callable $get) {
                                        $categoryId = $get('category_id');
                                        if ($categoryId) {
                                            return Subcategory::where('category_id', $categoryId)
                                                ->pluck('name', 'id');
                                        }
                                        return [];
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('sub_subcategory_id', null)),
                                
                                Forms\Components\Select::make('sub_subcategory_id')
                                    ->label('Под-подкатегория')
                                    ->options(function (callable $get) {
                                        $subcategoryId = $get('subcategory_id');
                                        if ($subcategoryId) {
                                            return Sub_Subcategory::where('subcategory_id', $subcategoryId)
                                                ->pluck('name', 'id');
                                        }
                                        return [];
                                    })
                                    ->required()
                                    ->afterStateUpdated(fn ($record) => $record?->clearCache()),
                            ]),
                    ]),

                Forms\Components\Section::make('Изображения')
                    ->schema([
                        Forms\Components\FileUpload::make('images')
                            ->label('Изображения товара')
                            ->image()
                            ->multiple()
                            ->directory('products')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->reorderable()
                            ->appendFiles()
                            ->afterStateUpdated(fn ($record) => $record?->clearCache())
                            ->columnSpanFull(),
                        
                        Forms\Components\Placeholder::make('existing_images')
                            ->label('Текущие изображения')
                            ->content(function ($record) {
                                if (!$record || $record->images->isEmpty()) return 'Нет изображений';
                                
                                $html = '<div class="grid grid-cols-4 gap-4">';
                                foreach ($record->images as $image) {
                                    $type = [];
                                    if ($image->is_main) $type[] = 'Главное';
                                    if ($image->is_background) $type[] = 'Фоновое';
                                    $typeLabel = $type ? ' (' . implode(', ', $type) . ')' : '';
                                    
                                    $html .= '<div class="border rounded p-2">';
                                    $html .= '<img src="/storage/' . $image->path . '" class="w-full h-32 object-cover mb-2">';
                                    $html .= '<div class="text-xs text-center">' . $typeLabel . '</div>';
                                    $html .= '<div class="text-xs text-center text-gray-500">порядок: ' . $image->sort_order . '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Склад и остатки')
                    ->schema([
                        Forms\Components\Repeater::make('inventories')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('warehouse_id')
                                            ->label('Склад')
                                            ->relationship('warehouse', 'name')
                                            ->required()
                                            ->searchable()
                                            ->afterStateUpdated(fn ($record) => $record?->product?->clearCache()),
                                        
                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Количество')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->afterStateUpdated(fn ($record) => $record?->product?->updateCacheFields()),
                                        
                                        Forms\Components\DatePicker::make('last_restock_date')
                                            ->label('Дата последней поставки'),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Поставщики')
                    ->schema([
                        Forms\Components\Repeater::make('suppliers')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\Select::make('supplier_id')
                                            ->label('Поставщик')
                                            ->relationship('supplier', 'name')
                                            ->required()
                                            ->searchable(),
                                        
                                        Forms\Components\TextInput::make('cost_price')
                                            ->label('Закупочная цена')
                                            ->numeric()
                                            ->prefix('₽'),
                                        
                                        Forms\Components\TextInput::make('lead_time_days')
                                            ->label('Срок поставки (дней)')
                                            ->numeric(),
                                        
                                        Forms\Components\TextInput::make('min_order_quantity')
                                            ->label('Мин. заказ')
                                            ->numeric()
                                            ->default(1),
                                        
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Активен')
                                            ->default(true),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('main_image')
                    ->label('Фото')
                    ->circular()
                    ->getStateUsing(fn ($record) => self::getData($record)['main_image'])
                    ->defaultImageUrl(url('/images/default-product.jpg')),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->weight('bold'),
                
                TextColumn::make('category_path')
                    ->label('Категория')
                    ->getStateUsing(function ($record) {
                        $path = self::getData($record)['category_path'];
                        return $path ? $path['category'] . ' → ' . $path['subcategory'] . ' → ' . $path['sub_subcategory'] : '—';
                    })
                    ->toggleable(),
                
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                
                TextColumn::make('total_quantity')
                    ->label('Остаток')
                    ->getStateUsing(fn ($record) => self::getData($record)['total_quantity'])
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->badge(),
                
                TextColumn::make('sold_count')
                    ->label('Продано')
                    ->getStateUsing(fn ($record) => self::getData($record)['sold_count'])
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->getStateUsing(fn ($record) => self::getData($record)['created_at'])
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Категория')
                    ->relationship('sub_subcategories.subcategory.category', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('brand')
                    ->label('Бренд')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('in_stock')
                    ->label('В наличии')
                    ->query(fn (Builder $query): Builder => $query->whereHas('inventories', fn ($q) => $q->where('quantity', '>', 0))),
                
                Filter::make('out_of_stock')
                    ->label('Нет в наличии')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('inventories', fn ($q) => $q->where('quantity', '>', 0))),
                
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('С даты'),
                        Forms\Components\DatePicker::make('created_until')->label('По дату'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
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
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }

}