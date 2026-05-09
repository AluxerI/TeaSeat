<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use App\Traits\HasNavigationBadge;
use Filament\Notifications\Notification;

class InventoryResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Inventory::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Склад и поставщики';
    protected static ?string $navigationLabel = 'Остатки';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product', 'warehouse']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация об остатке')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([    
                                Forms\Components\Select::make('warehouse_id')
                                    ->label('Склад')
                                    ->relationship('warehouse', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn ($record) => $record !== null)
                                    ->rules([
                                        fn ($get, $record) => 
                                            'unique:inventories,warehouse_id,' . ($record?->id ?? 'NULL') . ',id,product_id,' . $get('product_id')
                                    ])
                                    ->validationMessages([
                                        'unique' => 'Такой товар уже есть на этом складе',
                                    ]),
                                
                                Forms\Components\Select::make('product_id')
                                    ->label('Товар')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn ($record) => $record !== null)
                                    ->rules([
                                        fn ($get, $record) => 
                                            'unique:inventories,product_id,' . ($record?->id ?? 'NULL') . ',id,warehouse_id,' . $get('warehouse_id')
                                    ])
                                    ->validationMessages([
                                        'unique' => 'Такой товар уже есть на этом складе',
                                    ]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Количество (штук)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->helperText('Для онлайн-продаж'),

                                // Скрытое поле для оффлайн кассы (не отображаем в админке)
                                Forms\Components\Hidden::make('weight_quantity')
                                    ->default(0),
                                
                                Forms\Components\DatePicker::make('last_restock_date')
                                    ->label('Дата последней поставки')
                                    ->nullable()
                                    ->maxDate(now()),
                            ]),
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

                TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->weight('bold'),

                TextColumn::make('product.price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('warehouse.name')
                    ->label('Склад')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.city')
                    ->label('Город')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('quantity')
                    ->label('Количество (шт)')
                    ->sortable()
                    ->alignCenter()
                    ->color(fn ($state) => ($state ?? 0) > 0 ? 'success' : 'danger')
                    ->badge(),

                TextColumn::make('last_restock_date')
                    ->label('Последняя поставка')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Склад')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('in_stock')
                    ->label('В наличии')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('quantity', '>', 0)
                    ),
                
                Filter::make('out_of_stock')
                    ->label('Нет в наличии')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('quantity', '<=', 0)
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->product) {
                            $record->product->updateCacheFields();
                            $record->product->clearCache();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->product) {
                                    $record->product->updateCacheFields();
                                    $record->product->clearCache();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('last_restock_date', 'desc')
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
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}