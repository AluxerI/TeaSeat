<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Управление товарами';

    protected static ?string $modelLabel = 'Товар';

    protected static ?string $pluralModelLabel = 'Товары';

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
                                    ->label('Название товара')
                                    ->required()
                                    ->maxLength(255),
                                
                                TextInput::make('price')
                                    ->label('Цена')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₽')
                                    ->step(0.01),
                                
                                Select::make('brand_id')
                                    ->label('Бренд')
                                    ->relationship('brand', 'name')
                                    ->searchable()
                                    ->preload(),
                                
                                TextInput::make('weight_grams')
                                    ->label('Вес (грамм)')
                                    ->numeric()
                                    ->suffix('г'),
                                
                                TextInput::make('sold_count')
                                    ->label('Продано')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                        
                        RichEditor::make('description')
                            ->label('Описание')
                            ->columnSpanFull(),
                        
                        Textarea::make('ingredients')
                            ->label('Ингредиенты/Состав')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Категория')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('category_id')
                                    ->label('Категория')
                                    ->options(Category::pluck('name', 'id'))
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('subcategory_id', null)),
                                
                                Select::make('subcategory_id')
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
                                
                                Select::make('sub_subcategory_id')
                                    ->label('Под-подкатегория')
                                    ->options(function (callable $get) {
                                        $subcategoryId = $get('subcategory_id');
                                        if ($subcategoryId) {
                                            return Sub_Subcategory::where('subcategory_id', $subcategoryId)
                                                ->pluck('name', 'id');
                                        }
                                        return [];
                                    })
                                    ->required(),
                            ]),
                    ]),

                Section::make('Изображения')
                    ->schema([
                        FileUpload::make('images')
                            ->label('Изображения товара')
                            ->image()
                            ->multiple()
                            ->directory('products')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->reorderable()
                            ->appendFiles()
                            ->columnSpanFull(),
                        
                        Forms\Components\Placeholder::make('existing_images')
                            ->label('Текущие изображения')
                            ->content(function ($record) {
                                if (!$record || $record->images->isEmpty()) {
                                    return 'Нет изображений';
                                }
                                
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

                Section::make('Склад и остатки')
                    ->schema([
                        Repeater::make('inventories')
                            ->relationship()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('warehouse_id')
                                            ->label('Склад')
                                            ->relationship('warehouse', 'name')
                                            ->required()
                                            ->searchable(),
                                        
                                        TextInput::make('quantity')
                                            ->label('Количество')
                                            ->numeric()
                                            ->required()
                                            ->default(0),
                                        
                                        TextInput::make('last_restock_date')
                                            ->label('Дата последней поставки')
                                            ->date(),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Section::make('Поставщики')
                    ->schema([
                        Repeater::make('suppliers')
                            ->relationship()
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        Select::make('supplier_id')
                                            ->label('Поставщик')
                                            ->relationship('supplier', 'name')
                                            ->required()
                                            ->searchable(),
                                        
                                        TextInput::make('cost_price')
                                            ->label('Закупочная цена')
                                            ->numeric()
                                            ->prefix('₽'),
                                        
                                        TextInput::make('lead_time_days')
                                            ->label('Срок поставки (дней)')
                                            ->numeric(),
                                        
                                        TextInput::make('min_order_quantity')
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
                ImageColumn::make('main_image_url')
                    ->label('Фото')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-product.jpg')),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                TextColumn::make('category_path')
                    ->label('Категория')
                    ->formatStateUsing(function ($record) {
                        $path = $record->getMainSubSubcategoryAttribute();
                        if ($path && $path->subcategory && $path->subcategory->category) {
                            return $path->subcategory->category->name . ' → ' .
                                   $path->subcategory->name . ' → ' .
                                   $path->name;
                        }
                        return '—';
                    })
                    ->searchable(false)
                    ->toggleable(),
                
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                
                TextColumn::make('total_quantity')
                    ->label('Остаток')
                    ->getStateUsing(fn ($record) => $record->inventories->sum('quantity'))
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                
                TextColumn::make('sold_count')
                    ->label('Продано')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereHas('inventories', fn ($q) => $q->where('quantity', '>', 0))->count() ?: null;
    }
}