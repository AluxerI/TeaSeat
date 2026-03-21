<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;
use Filament\Notifications\Notification;

class PromotionResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Promotion::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Маркетинг';
    protected static ?string $navigationLabel = 'Акции';

    protected static ?string $modelLabel = 'Акция';
    protected static ?string $pluralModelLabel = 'Акции';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['products', 'categories', 'subcategories', 'subSubcategories']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Акция')
                    ->tabs([
                        Tab::make('Основная информация')
                            ->schema([
                                Section::make('Основная информация')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Название акции')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(ignoreRecord: true),
                                                
                                                // 👈 ТИП ТОЛЬКО PRODUCT ДЛЯ ТОВАРНЫХ АКЦИЙ
                                                Select::make('type')
                                                    ->label('Тип акции')
                                                    ->options([
                                                        'product' => 'На товары',
                                                        'cart' => 'На корзину',
                                                        'shipping' => 'На доставку',
                                                    ])
                                                    ->required()
                                                    ->default('product')
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        $set('categories', []);
                                                        $set('subcategories', []);
                                                        $set('sub_subcategories', []);
                                                        $set('products', []);
                                                    }),
                                                
                                                TextInput::make('code')
                                                    ->label('Промокод')
                                                    ->maxLength(50)
                                                    ->unique(ignoreRecord: true)
                                                    ->helperText('Оставьте пустым, если акция без промокода')
                                                    ->visible(fn ($get) => in_array($get('type'), ['cart', 'shipping'])),
                                                
                                                TextInput::make('discount_percent')
                                                    ->label('Процент скидки')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->step(0.01)
                                                    ->suffix('%'),
                                                
                                                Toggle::make('is_active')
                                                    ->label('Активна')
                                                    ->default(true),
                                            ]),
                                        
                                        Textarea::make('description')
                                            ->label('Описание')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Период действия')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                DateTimePicker::make('start_date')
                                                    ->label('Дата начала')
                                                    ->nullable()
                                                    ->native(false)
                                                    ->displayFormat('d.m.Y H:i'),
                                                
                                                DateTimePicker::make('end_date')
                                                    ->label('Дата окончания')
                                                    ->nullable()
                                                    ->native(false)
                                                    ->displayFormat('d.m.Y H:i')
                                                    ->after('start_date'),
                                            ]),
                                    ]),

                                Section::make('Дополнительные условия')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('min_order_amount')
                                                    ->label('Минимальная сумма заказа')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(100)
                                                    ->prefix('₽')
                                                    ->visible(fn ($get) => in_array($get('type'), ['cart', 'shipping'])),
                                                
                                                TextInput::make('usage_limit')
                                                    ->label('Лимит использований')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(null)
                                                    ->helperText('0 или пусто - без лимита')
                                                    ->visible(fn ($get) => $get('code') !== null),
                                                
                                                TextInput::make('used_count')
                                                    ->label('Использовано раз')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->visible(fn ($record) => $record !== null),
                                            ]),
                                    ])
                                    ->visible(fn ($get) => in_array($get('type'), ['cart', 'shipping'])),
                            ]),

                        // 👈 ВСЕ ЭТИ ВКЛАДКИ ВИДНЫ ТОЛЬКО ПРИ TYPE = 'product'
                        Tab::make('Категории')
                            ->schema([
                                Section::make('Категории, на которые действует акция')
                                    ->schema([
                                        Select::make('categories')
                                            ->label('Категории')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->options(function () {
                                                return Category::select('id', 'name')
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                                if ($record) {
                                                    $record->clearCache();
                                                }
                                            }),
                                    ])
                                    ->visible(fn ($get) => $get('type') === 'product'),
                            ]),

                        Tab::make('Подкатегории')
                            ->schema([
                                Section::make('Подкатегории, на которые действует акция')
                                    ->schema([
                                        Select::make('subcategories')
                                            ->label('Подкатегории')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->options(function () {
                                                return Subcategory::with('category')
                                                    ->select('id', 'name', 'category_id')
                                                    ->orderBy('name')
                                                    ->get()
                                                    ->mapWithKeys(function ($item) {
                                                        $label = ($item->category?->name ?? '—') . ' → ' . $item->name;
                                                        return [$item->id => $label];
                                                    });
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                                if ($record) {
                                                    $record->clearCache();
                                                }
                                            }),
                                    ])
                                    ->visible(fn ($get) => $get('type') === 'product'),
                            ]),

                        Tab::make('Под-подкатегории')
                            ->schema([
                                Section::make('Под-подкатегории, на которые действует акция')
                                    ->schema([
                                        Select::make('sub_subcategories')
                                            ->label('Под-подкатегории')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->options(function () {
                                                return Sub_Subcategory::with('subcategory.category')
                                                    ->select('id', 'name', 'subcategory_id')
                                                    ->orderBy('name')
                                                    ->get()
                                                    ->mapWithKeys(function ($item) {
                                                        $label = ($item->subcategory?->category?->name ?? '—') . ' → ' . 
                                                                 ($item->subcategory?->name ?? '—') . ' → ' . 
                                                                 $item->name;
                                                        return [$item->id => $label];
                                                    });
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                                if ($record) {
                                                    $record->clearCache();
                                                }
                                            }),
                                    ])
                                    ->visible(fn ($get) => $get('type') === 'product'),
                            ]),

                        Tab::make('Товары')
                            ->schema([
                                Section::make('Товары, участвующие в акции')
                                    ->schema([
                                        Select::make('products')
                                            ->label('Товары')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->options(function () {
                                                return Product::select('id', 'name')
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                                if ($record) {
                                                    $record->clearCache();
                                                }
                                            }),
                                        
                                        Forms\Components\Placeholder::make('products_count')
                                            ->label('Товаров в акции')
                                            ->content(fn ($record) => $record ? $record->products()->count() : 0)
                                            ->visible(fn ($get, $record) => $record && $get('type') === 'product'),
                                    ])
                                    ->visible(fn ($get) => $get('type') === 'product'),
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
                
                TextColumn::make('code')
                    ->label('Промокод')
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->copyable()
                    ->copyMessage('Промокод скопирован')
                    ->toggleable(),
                
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'product' => 'На товары',
                        'cart' => 'На корзину',
                        'shipping' => 'На доставку',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'product' => 'success',
                        'cart' => 'info',
                        'shipping' => 'warning',
                        default => 'gray',
                    }),
                
                TextColumn::make('discount_percent')
                    ->label('Скидка')
                    ->suffix('%')
                    ->sortable()
                    ->alignCenter(),
                
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->counts('products')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('categories_count')
                    ->label('Категорий')
                    ->counts('categories')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('subcategories_count')
                    ->label('Подкатегорий')
                    ->counts('subcategories')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('sub_subcategories_count')
                    ->label('Под-подкатегорий')
                    ->counts('subSubcategories')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('used_count')
                    ->label('Использовано')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
                TextColumn::make('start_date')
                    ->label('С')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('end_date')
                    ->label('По')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'product' => 'На товары',
                        'cart' => 'На корзину',
                        'shipping' => 'На доставку',
                    ]),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                
                Filter::make('has_code')
                    ->label('С промокодом')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('code')),
                
                Filter::make('active_period')
                    ->label('Действующие')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                    })->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->products()->count() > 0 || 
                            $record->categories()->count() > 0 || 
                            $record->subcategories()->count() > 0 || 
                            $record->subSubcategories()->count() > 0) {
                            Notification::make()
                                ->title('Невозможно удалить акцию')
                                ->body('У этой акции есть привязанные товары или категории.')
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
                                if ($record->products()->count() > 0 || 
                                    $record->categories()->count() > 0 || 
                                    $record->subcategories()->count() > 0 || 
                                    $record->subSubcategories()->count() > 0) {
                                    Notification::make()
                                        ->title('Невозможно удалить некоторые акции')
                                        ->body('Акции с привязанными товарами или категориями не могут быть удалены.')
                                        ->danger()
                                        ->send();

                                    $this->halt();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
            'view' => Pages\ViewPromotion::route('/{record}'),
        ];
    }
}