<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use App\Traits\HasNavigationBadge;
use Filament\Notifications\Notification;

class CategoryResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Управление каталогом';
    protected static ?string $navigationLabel = 'Категории';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['images'])
            ->withCount(['subcategories'])
            ->addSelect([
                'products_count' => Product::selectRaw('COUNT(DISTINCT products.id)')
                    ->join('sub_subcategory_products', 'products.id', '=', 'sub_subcategory_products.product_id')
                    ->join('sub_subcategories', 'sub_subcategory_products.sub_subcategory_id', '=', 'sub_subcategories.id')
                    ->join('subcategories', 'sub_subcategories.subcategory_id', '=', 'subcategories.id')
                    ->whereColumn('subcategories.category_id', 'categories.id')
            ]);
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
                                    ->label('Название категории')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                
                                Forms\Components\FileUpload::make('icon')
                                    ->label('Иконка')
                                    ->image()
                                    ->directory('category-icons/categories')
                                    ->visibility('public')
                                    ->maxSize(1024)
                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                    ->helperText('Иконка категории (рекомендуется SVG)')
                                    ->afterStateUpdated(function ($state, $record) {
                                        if ($record) {
                                            $record->clearCache();
                                        }
                                    }),
                            ]),
                    ]),

                Forms\Components\Section::make('Изображения')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\FileUpload::make('main_image')
                                    ->label('Главное изображение')
                                    ->image()
                                    ->directory('category-images/categories')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->afterStateUpdated(function ($state, $record) {
                                        if ($record && $state) {
                                            $record->images()->updateOrCreate(
                                                ['is_main' => true],
                                                [
                                                    'path' => $state,
                                                    'disk' => 'public',
                                                    'is_main' => true,
                                                ]
                                            );
                                            $record->clearCache();
                                        }
                                    }),
                                
                                Forms\Components\FileUpload::make('background_image')
                                    ->label('Фоновое изображение')
                                    ->image()
                                    ->directory('category-images/categories')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->afterStateUpdated(function ($state, $record) {
                                        if ($record && $state) {
                                            $record->images()->updateOrCreate(
                                                ['is_background' => true],
                                                [
                                                    'path' => $state,
                                                    'disk' => 'public',
                                                    'is_background' => true,
                                                ]
                                            );
                                            $record->clearCache();
                                        }
                                    }),
                            ]),
                        
                        Forms\Components\Placeholder::make('existing_images')
                            ->label('Текущие изображения')
                            ->content(function ($record) {
                                if (!$record || $record->images->isEmpty()) {
                                    return 'Нет изображений';
                                }
                                
                                $html = '<div class="grid grid-cols-2 gap-4">';
                                foreach ($record->images as $image) {
                                    $type = [];
                                    if ($image->is_main) $type[] = 'Главное';
                                    if ($image->is_background) $type[] = 'Фоновое';
                                    $typeLabel = $type ? ' (' . implode(', ', $type) . ')' : '';
                                    
                                    $html .= '<div class="border rounded p-2">';
                                    $html .= '<img src="' . $image->image_url . '" class="w-full h-32 object-cover mb-2">';
                                    $html .= '<div class="text-xs text-center">' . $typeLabel . '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
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
                    ->toggleable(isToggledHiddenByDefault: true),

                ImageColumn::make('main_image')
                    ->label('Изображение')
                    ->circular()
                    ->getStateUsing(fn ($record) => $record->getMainImageUrl())
                    ->defaultImageUrl(url('/images/default-category.jpg')),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                ImageColumn::make('icon')
                    ->label('Иконка')
                    ->circular()
                    ->getStateUsing(fn ($record) => $record->icon_url)
                    ->defaultImageUrl(url('/images/default-icon.png')),

                // ✅ Используем withCount из запроса
                TextColumn::make('subcategories_count')
                    ->label('Подкатегории')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                // ✅ Используем addSelect из запроса
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->subcategories()->count() > 0) {
                            Notification::make()
                                ->title('Невозможно удалить категорию')
                                ->body('У этой категории есть подкатегории. Сначала удалите их.')
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
                                if ($record->subcategories()->count() > 0) {
                                    Notification::make()
                                        ->title('Невозможно удалить некоторые категории')
                                        ->body('Категории с подкатегориями не могут быть удалены.')
                                        ->danger()
                                        ->send();

                                    $this->halt();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc')
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
            'view' => Pages\ViewCategory::route('/{record}'),
        ];
    }
}