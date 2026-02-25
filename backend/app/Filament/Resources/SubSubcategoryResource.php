<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubSubcategoryResource\Pages;
use App\Models\Sub_Subcategory;
use App\Models\Subcategory;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;

class SubSubcategoryResource extends Resource
{
    protected static ?string $model = Sub_Subcategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Управление каталогом';

    protected static ?string $navigationLabel = 'Под-подкатегории';

    protected static ?string $modelLabel = 'Под-подкатегория';

    protected static ?string $pluralModelLabel = 'Под-подкатегории';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Под-подкатегория')
                    ->tabs([
                        Tabs\Tab::make('Основная информация')
                            ->schema([
                                Section::make('Данные')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('subcategory_id')
                                                    ->label('Родительская подкатегория')
                                                    ->relationship('subcategory', 'name')
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        $set('category_id', Subcategory::find($state)?->category_id);
                                                    }),
                                                
                                                TextInput::make('name')
                                                    ->label('Название')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug())),
                                                
                                                TextInput::make('slug')
                                                    ->label('URL-алиас')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(ignoreRecord: true),
                                                
                                                FileUpload::make('icon')
                                                    ->label('Иконка')
                                                    ->image()
                                                    ->directory('category-icons/sub-subcategories')
                                                    ->visibility('public')
                                                    ->maxSize(1024)
                                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg']),
                                            ]),
                                        
                                        Forms\Components\Placeholder::make('category_info')
                                            ->label('Полный путь')
                                            ->content(function ($get, $record) {
                                                $subcategoryId = $get('subcategory_id') ?? ($record?->subcategory_id);
                                                if (!$subcategoryId) {
                                                    return 'Выберите подкатегорию';
                                                }
                                                
                                                $subcategory = Subcategory::with('category')->find($subcategoryId);
                                                if ($subcategory && $subcategory->category) {
                                                    return $subcategory->category->name . ' → ' . $subcategory->name;
                                                }
                                                
                                                return 'Путь не найден';
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        
                        Tabs\Tab::make('Товары')
                            ->schema([
                                Select::make('products')
                                    ->label('Товары в категории')
                                    ->multiple()
                                    ->relationship('products', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->options(function () {
                                        return Product::limit(100)->pluck('name', 'id');
                                    })
                                    ->helperText('Выберите товары, которые относятся к этой под-подкатегории')
                                    ->columnSpanFull(),
                                
                                Forms\Components\Placeholder::make('products_list')
                                    ->label('Текущие товары')
                                    ->content(function ($record) {
                                        if (!$record || $record->products->isEmpty()) {
                                            return 'Нет товаров в этой категории';
                                        }
                                        
                                        $html = '<div class="grid grid-cols-3 gap-2">';
                                        foreach ($record->products->take(12) as $product) {
                                            $html .= '<div class="text-sm bg-gray-100 px-2 py-1 rounded">' . $product->name . '</div>';
                                        }
                                        if ($record->products->count() > 12) {
                                            $html .= '<div class="text-sm text-gray-500">и еще ' . ($record->products->count() - 12) . ' товаров...</div>';
                                        }
                                        $html .= '</div>';
                                        
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->columnSpanFull(),
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
                    ->sortable(),
                
                TextColumn::make('subcategory.name')
                    ->label('Подкатегория')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('subcategory.category.name')
                    ->label('Категория')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->counts('products')
                    ->sortable()
                    ->alignCenter(),
                
                ImageColumn::make('icon_url')
                    ->label('Иконка')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-icon.png')),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Категория')
                    ->relationship('subcategory.category', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('subcategory')
                    ->label('Подкатегория')
                    ->relationship('subcategory', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListSubSubcategories::route('/'),
            'create' => Pages\CreateSubSubcategory::route('/create'),
            'edit' => Pages\EditSubSubcategory::route('/{record}/edit'),
            'view' => Pages\ViewSubSubcategory::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }
}