<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountResource\Pages;
use App\Models\Discount;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use App\Models\Product;
use App\Models\User;
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
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;
use Filament\Notifications\Notification;

class DiscountResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Discount::class;
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationGroup = 'Маркетинг';
    protected static ?string $navigationLabel = 'Персональные скидки';
    protected static ?string $modelLabel = 'Скидка';
    protected static ?string $pluralModelLabel = 'Скидки';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['users', 'products', 'categories', 'subcategories', 'subSubcategories']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Скидка')
                    ->tabs([
                        Tab::make('Основная информация')
                            ->schema([
                                Section::make('Параметры скидки')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Название скидки')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(ignoreRecord: true),
                                                
                                                Select::make('type')
                                                    ->label('Тип скидки')
                                                    ->options([
                                                        'personal' => 'Персональная',
                                                        'first_order' => 'Первый заказ',
                                                        'loyalty' => 'Программа лояльности',
                                                        'referral' => 'Реферальная',
                                                        'category' => 'На категорию',
                                                        'subcategory' => 'На подкатегорию',
                                                        'sub_subcategory' => 'На под-подкатегорию',
                                                    ])
                                                    ->required()
                                                    ->default('personal')
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        $set('is_global', false);
                                                        $set('categories', []);
                                                        $set('subcategories', []);
                                                        $set('sub_subcategories', []);
                                                        $set('products', []);
                                                        $set('users', []);
                                                    }),
                                                
                                                TextInput::make('value')
                                                    ->label('Размер скидки')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->step(0.01)
                                                    ->suffix('%'),
                                                
                                                Toggle::make('is_global')
                                                    ->label('Глобальная скидка')
                                                    ->helperText('Применяется ко всем товарам')
                                                    ->reactive()
                                                    ->default(false)
                                                    ->visible(fn ($get) => $get('type') === 'personal'),
                                                
                                                Toggle::make('is_active')
                                                    ->label('Активна')
                                                    ->default(true),
                                            ]),
                                    ]),

                                Section::make('Период действия')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                DateTimePicker::make('start_at')
                                                    ->label('Дата начала')
                                                    ->nullable()
                                                    ->native(false)
                                                    ->displayFormat('d.m.Y H:i'),
                                                
                                                DateTimePicker::make('end_at')
                                                    ->label('Дата окончания')
                                                    ->nullable()
                                                    ->native(false)
                                                    ->displayFormat('d.m.Y H:i')
                                                    ->after('start_at'),
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
                                                    ->nullable(),
                                                
                                                TextInput::make('usage_limit')
                                                    ->label('Лимит использований')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->default(1)
                                                    ->helperText('Сколько раз можно использовать'),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Категории')
                            ->schema([
                                Section::make('Скидка на категории')
                                    ->schema([
                                        Select::make('categories')
                                            ->label('Категории')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->visible(fn ($get) => $get('type') === 'category')
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
                                        
                                        Forms\Components\Placeholder::make('categories_count')
                                            ->label('Категорий выбрано')
                                            ->content(fn ($record) => $record ? $record->categories()->count() : 0)
                                            ->visible(fn ($get, $record) => $record && $get('type') === 'category'),
                                    ])
                                    ->visible(fn ($get) => $get('type') === 'category'),
                            ]),

                        Tab::make('Подкатегории')
                            ->schema([
                                Section::make('Скидка на подкатегории')
                                    ->schema([
                                        Select::make('subcategories')
                                            ->label('Подкатегории')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->visible(fn ($get) => $get('type') === 'subcategory')
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
                                        
                                        Forms\Components\Placeholder::make('subcategories_count')
                                            ->label('Подкатегорий выбрано')
                                            ->content(fn ($record) => $record ? $record->subcategories()->count() : 0)
                                            ->visible(fn ($get, $record) => $record && $get('type') === 'subcategory'),
                                    ])
                                    ->visible(fn ($get) => $get('type') === 'subcategory'),
                            ]),

                        Tab::make('Под-подкатегории')
                            ->schema([
                                Section::make('Скидка на под-подкатегории')
                                    ->schema([
                                        Select::make('sub_subcategories')
                                            ->label('Под-подкатегории')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->visible(fn ($get) => $get('type') === 'sub_subcategory')
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
                                        
                                        Forms\Components\Placeholder::make('sub_subcategories_count')
                                            ->label('Под-подкатегорий выбрано')
                                            ->content(fn ($record) => $record ? $record->subSubcategories()->count() : 0)
                                            ->visible(fn ($get, $record) => $record && $get('type') === 'sub_subcategory'),
                                    ])
                                    ->visible(fn ($get) => $get('type') === 'sub_subcategory'),
                            ]),

                        Tab::make('Товары')
                            ->schema([
                                Section::make('Товары, на которые действует скидка')
                                    ->schema([
                                        Select::make('products')
                                            ->label('Товары')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->visible(fn ($get) => !$get('is_global') && in_array($get('type'), ['personal', 'first_order', 'loyalty', 'referral']))
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
                                            ->label('Товаров со скидкой')
                                            ->content(fn ($record) => $record ? $record->products()->count() : 0)
                                            ->visible(fn ($record) => $record && !$record->is_global),
                                    ]),
                            ]),

                        Tab::make('Пользователи')
                            ->schema([
                                Section::make('Пользователи, имеющие скидку')
                                    ->schema([
                                        Select::make('users')
                                            ->label('Пользователи')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->optionsLimit(100)
                                            ->dehydrated(false)
                                            ->columnSpanFull()
                                            ->options(function () {
                                                return User::orderBy('name')
                                                    ->select('id', 'name', 'email')
                                                    ->get()
                                                    ->mapWithKeys(function ($item) {
                                                        $label = $item->name . ' (' . $item->email . ')';
                                                        return [$item->id => $label];
                                                    });
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                                if ($record) {
                                                    $record->clearCache();
                                                }
                                            }),
                                        
                                        Forms\Components\Placeholder::make('users_count')
                                            ->label('Пользователей со скидкой')
                                            ->content(fn ($record) => $record ? $record->users()->count() : 0)
                                            ->visible(fn ($record) => $record !== null),
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
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'personal' => 'Персональная',
                        'first_order' => 'Первый заказ',
                        'loyalty' => 'Лояльность',
                        'referral' => 'Реферальная',
                        'category' => 'На категорию',
                        'subcategory' => 'На подкатегорию',
                        'sub_subcategory' => 'На под-подкатегорию',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'personal' => 'primary',
                        'first_order' => 'success',
                        'loyalty' => 'warning',
                        'referral' => 'info',
                        'category' => 'danger',
                        'subcategory' => 'purple',
                        'sub_subcategory' => 'pink',
                        default => 'gray',
                    }),
                
                TextColumn::make('value')
                    ->label('Размер')
                    ->suffix('%')
                    ->sortable()
                    ->alignCenter(),
                
                IconColumn::make('is_global')
                    ->label('Глобальная')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-cube')
                    ->trueColor('success')
                    ->falseColor('gray'),
                
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                TextColumn::make('users_count')
                    ->label('Пользователей')
                    ->counts('users')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                
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
                
                TextColumn::make('start_at')
                    ->label('С')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('end_at')
                    ->label('По')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'personal' => 'Персональная',
                        'first_order' => 'Первый заказ',
                        'loyalty' => 'Лояльность',
                        'referral' => 'Реферальная',
                        'category' => 'На категорию',
                        'subcategory' => 'На подкатегорию',
                        'sub_subcategory' => 'На под-подкатегорию',
                    ]),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                
                Filter::make('active_period')
                    ->label('Действующие')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereNull('start_at')->orWhere('start_at', '<=', now());
                    })->where(function ($q) {
                        $q->whereNull('end_at')->orWhere('end_at', '>=', now());
                    })),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->users()->count() > 0 || 
                            $record->products()->count() > 0 ||
                            $record->categories()->count() > 0 ||
                            $record->subcategories()->count() > 0 ||
                            $record->subSubcategories()->count() > 0) {
                            Notification::make()
                                ->title('Невозможно удалить скидку')
                                ->body('У этой скидки есть привязанные пользователи, товары или категории.')
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
                                if ($record->users()->count() > 0 || 
                                    $record->products()->count() > 0 ||
                                    $record->categories()->count() > 0 ||
                                    $record->subcategories()->count() > 0 ||
                                    $record->subSubcategories()->count() > 0) {
                                    Notification::make()
                                        ->title('Невозможно удалить некоторые скидки')
                                        ->body('Скидки с привязанными пользователями, товарами или категориями не могут быть удалены.')
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
            'index' => Pages\ListDiscounts::route('/'),
            'create' => Pages\CreateDiscount::route('/create'),
            'edit' => Pages\EditDiscount::route('/{record}/edit'),
            'view' => Pages\ViewDiscount::route('/{record}'),
        ];
    }
}