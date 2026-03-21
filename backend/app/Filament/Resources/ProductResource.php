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
                                                    ->label('Цена')
                                                    ->required()
                                                    ->numeric()
                                                    ->prefix('₽')
                                                    ->minValue(0)
                                                    ->step(0.01),
                                                
                                                Forms\Components\TextInput::make('weight_grams')
                                                    ->label('Вес (грамм)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('г'),
                                                
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
                        
                        Forms\Components\Tabs\Tab::make('Категория')
                            ->schema([
                                Forms\Components\Section::make('Категория')
                                    ->schema([
                                        // ✅ Используем отношение – это не создаст поле в products
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
                                                                // Если товар уже существует, используем его ID
                                                                if ($record) {
                                                                    return 'products/' . $record->product_id;
                                                                }
                                                                // Для нового товара используем временную папку
                                                                return 'products/temp';
                                                            })
                                                            ->visibility('public')
                                                            ->maxSize(2048)
                                                            ->columnSpan(2)
                                                            ->required()
                                                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                                                // Если файл загружен и товар новый, запоминаем временный путь
                                                                if (!$record && $state) {
                                                                    // Временный файл будет обработан после создания товара
                                                                }
                                                            }),
                                                        
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
                    ->sortable(),
                
                TextColumn::make('total_quantity')
                    ->label('Остаток')
                    ->getStateUsing(fn ($record) => $record->total_quantity)
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
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'import' => Pages\ImportProducts::route('/import'),
            'import-progress' => Pages\ImportProgress::route('/import-progress/{batchId}'),
            'import-result' => Pages\ImportResult::route('/import-result/{batchId}'),
        ];
    }
}