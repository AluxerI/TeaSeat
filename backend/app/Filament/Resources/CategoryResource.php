<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use App\Traits\HasNavigationBadge;

class CategoryResource extends Resource
{
    use HasNavigationBadge;
    
    private static array $categoryDataCache = [];

    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Управление каталогом';
    protected static ?string $navigationLabel = 'Категории';

    /**
     * Получить данные категории с кешированием в памяти
     */
    private static function getCategoryData($record): array
    {
        $id = $record->id;
        if (!isset(self::$categoryDataCache[$id])) {
            self::$categoryDataCache[$id] = $record->getAllData();
        }
        return self::$categoryDataCache[$id];
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
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug())),
                                
                                Forms\Components\TextInput::make('slug')
                                    ->label('URL-алиас')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Автоматически генерируется из названия'),
                                
                                Forms\Components\FileUpload::make('icon')
                                    ->label('Иконка')
                                    ->image()
                                    ->directory('category-icons/categories')
                                    ->visibility('public')
                                    ->maxSize(1024)
                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                    ->helperText('Иконка категории (рекомендуется SVG)'),
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
                                    ->afterStateUpdated(function ($state, callable $set, $record) {
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
                                    ->afterStateUpdated(function ($state, callable $set, $record) {
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
                                    $html .= '<img src="/storage/' . $image->path . '" class="w-full h-32 object-cover mb-2">';
                                    $html .= '<div class="text-xs text-center">' . $typeLabel . '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Подкатегории')
                    ->schema([
                        Forms\Components\Repeater::make('subcategories')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Название')
                                            ->required()
                                            ->maxLength(255),
                                        
                                        Forms\Components\FileUpload::make('icon')
                                            ->label('Иконка')
                                            ->image()
                                            ->directory('category-icons/subcategories')
                                            ->visibility('public')
                                            ->maxSize(1024),
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
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                // ИЗОБРАЖЕНИЕ - используем main_image_url (URL для отображения)
                ImageColumn::make('main_image')
                    ->label('Изображение')
                    ->circular()
                    ->getStateUsing(fn ($record) => self::getCategoryData($record)['main_image_url'] ?? null)
                    ->defaultImageUrl(url('/images/default-category.jpg')),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                // ИКОНКА - используем icon_url из трейта HasIcon
                ImageColumn::make('icon')
                    ->label('Иконка')
                    ->circular()
                    ->getStateUsing(fn ($record) => self::getCategoryData($record)['icon_url'] ?? null)
                    ->defaultImageUrl(url('/images/default-icon.png')),
                
                TextColumn::make('subcategories_count')
                    ->label('Подкатегории')
                    ->getStateUsing(fn ($record) => self::getCategoryData($record)['subcategories_count'])
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->getStateUsing(fn ($record) => self::getCategoryData($record)['products_count'])
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