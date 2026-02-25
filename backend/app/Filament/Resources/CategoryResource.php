<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Управление каталогом';

    protected static ?string $navigationLabel = 'Категории';

    protected static ?string $modelLabel = 'Категория';

    protected static ?string $pluralModelLabel = 'Категории';

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
                                    ->label('Название категории')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug())),
                                
                                TextInput::make('slug')
                                    ->label('URL-алиас')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Автоматически генерируется из названия'),
                                
                                FileUpload::make('icon')
                                    ->label('Иконка')
                                    ->image()
                                    ->directory('category-icons/categories')
                                    ->visibility('public')
                                    ->maxSize(1024)
                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                    ->helperText('Иконка категории (рекомендуется SVG)'),
                            ]),
                    ]),

                Section::make('Изображения')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('main_image')
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
                                        }
                                    }),
                                
                                FileUpload::make('background_image')
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

                Section::make('Подкатегории')
                    ->schema([
                        Repeater::make('subcategories')
                            ->relationship()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Название')
                                            ->required()
                                            ->maxLength(255),
                                        
                                        FileUpload::make('icon')
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
                    ->toggleable(),
                
                ImageColumn::make('main_image_url')
                    ->label('Изображение')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-category.jpg')),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('subcategories_count')
                    ->label('Подкатегории')
                    ->counts('subcategories')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->getStateUsing(function ($record) {
                        return \App\Models\Product::whereHas('sub_subcategories.subcategory', function ($query) use ($record) {
                            $query->where('category_id', $record->id);
                        })->count();
                    })
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            ->defaultSort('name', 'asc');
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
            'view' => Pages\ViewCategory::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }
}