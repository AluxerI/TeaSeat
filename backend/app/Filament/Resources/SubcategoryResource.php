<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubcategoryResource\Pages;
use App\Models\Subcategory;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use App\Traits\HasNavigationBadge;
use Filament\Notifications\Notification;

class SubcategoryResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Subcategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Управление каталогом';
    protected static ?string $navigationLabel = 'Подкатегории';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['category'])
            ->withCount(['sub_subcategories'])
            ->addSelect([
                'products_count' => Product::selectRaw('COUNT(DISTINCT products.id)')
                    ->join('sub_subcategory_products', 'products.id', '=', 'sub_subcategory_products.product_id')
                    ->join('sub_subcategories', 'sub_subcategory_products.sub_subcategory_id', '=', 'sub_subcategories.id')
                    ->whereColumn('sub_subcategories.subcategory_id', 'subcategories.id')
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Подкатегория')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Основная информация')
                            ->schema([
                                Forms\Components\Section::make('Данные')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('category_id')
                                                    ->label('Родительская категория')
                                                    ->relationship('category', 'name')
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set, $record) {
                                                        if ($record) {
                                                            $record->clearCache();
                                                        }
                                                    }),
                                                
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Название подкатегории')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(ignoreRecord: true),
                                                
                                                Forms\Components\FileUpload::make('icon')
                                                    ->label('Иконка')
                                                    ->image()
                                                    ->directory('category-icons/subcategories')
                                                    ->visibility('public')
                                                    ->maxSize(1024)
                                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                                    ->afterStateUpdated(function ($state, $record) {
                                                        if ($record) {
                                                            $record->clearCache();
                                                        }
                                                    }),
                                            ]),
                                        
                                        Forms\Components\Placeholder::make('full_path')
                                            ->label('Полный путь')
                                            ->content(function ($record) {
                                                if (!$record) return '—';
                                                
                                                $category = $record->category?->name ?? '—';
                                                
                                                return "{$category} → {$record->name}";
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Под-подкатегории')
                            ->schema([
                                Forms\Components\Repeater::make('sub_subcategories')
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
                                                    ->directory('category-icons/sub-subcategories')
                                                    ->visibility('public')
                                                    ->maxSize(1024)
                                                    ->afterStateUpdated(function ($state, $record) {
                                                        if ($record) {
                                                            $record->clearCache();
                                                        }
                                                    }),
                                            ]),
                                    ])
                                    ->defaultItems(0)
                                    ->collapsible()
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
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('Категория')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sub_subcategories_count')
                    ->label('Под-подкатегории')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                ImageColumn::make('icon')
                    ->label('Иконка')
                    ->circular()
                    ->getStateUsing(fn ($record) => $record->icon_url)
                    ->defaultImageUrl(url('/images/default-icon.png')),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->sub_subcategories()->count() > 0) {
                            Notification::make()
                                ->title('Невозможно удалить подкатегорию')
                                ->body('У этой подкатегории есть под-подкатегории. Сначала удалите их.')
                                ->danger()
                                ->send();

                            $this->halt();
                        }
                        if ($record->getProductsCount() > 0) {
                            Notification::make()
                                ->title('Невозможно удалить подкатегорию')
                                ->body('У этой подкатегории есть товары. Сначала удалите или переназначьте товары.')
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
                                if ($record->sub_subcategories()->count() > 0) {
                                    Notification::make()
                                        ->title('Невозможно удалить некоторые подкатегории')
                                        ->body('Подкатегории с под-подкатегориями не могут быть удалены.')
                                        ->danger()
                                        ->send();

                                    $this->halt();
                                }
                                if ($record->getProductsCount() > 0) {
                                    Notification::make()
                                        ->title('Невозможно удалить некоторые подкатегории')
                                        ->body('Подкатегории с товарами не могут быть удалены.')
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
            'index' => Pages\ListSubcategories::route('/'),
            'create' => Pages\CreateSubcategory::route('/create'),
            'edit' => Pages\EditSubcategory::route('/{record}/edit'),
            'view' => Pages\ViewSubcategory::route('/{record}'),
        ];
    }
}