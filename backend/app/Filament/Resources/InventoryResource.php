<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use App\Models\Product;
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
                                    ->live()
                                    ->disabled(fn ($record) => $record !== null)
                                    ->rules([
                                        fn ($get, $record) => 
                                            'unique:inventories,product_id,' . ($record?->id ?? 'NULL') . ',id,warehouse_id,' . $get('warehouse_id')
                                    ])
                                    ->validationMessages([
                                        'unique' => 'Такой товар уже есть на этом складе',
                                    ]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Физический остаток')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->disabled(fn ($record) => $record !== null)
                                    ->suffix(function ($get) {
                                        $product = Product::find($get('product_id'));
                                        return $product?->isWeighted() ? 'г' : 'шт.';
                                    })
                                    ->helperText(fn ($record) => $record
                                        ? 'Для изменения используйте действие «Корректировать остаток» — оно потребует причину'
                                        : 'Остаток хранится в штуках или в целых граммах'),
                                
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
                    ->label('Физический остаток')
                    ->formatStateUsing(fn ($state, Inventory $record) => $state . ($record->product?->isWeighted() ? ' г' : ' шт.'))
                    ->sortable()
                    ->alignCenter()
                    ->color(fn ($state) => ($state ?? 0) > 0 ? 'success' : 'danger')
                    ->badge(),

                TextColumn::make('reserved_online_quantity')
                    ->label('Онлайн-резерв')
                    ->formatStateUsing(fn ($state, Inventory $record) => $state . ($record->product?->isWeighted() ? ' г' : ' шт.'))
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('reserved_seller_quantity')
                    ->label('Резерв продавца')
                    ->formatStateUsing(fn ($state, Inventory $record) => $state . ($record->product?->isWeighted() ? ' г' : ' шт.'))
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('available_quantity')
                    ->label('Свободный остаток')
                    ->getStateUsing(fn (Inventory $record) => $record->availableQuantity())
                    ->formatStateUsing(fn ($state, Inventory $record) => $state . ($record->product?->isWeighted() ? ' г' : ' шт.'))
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->badge()
                    ->alignCenter(),

                TextColumn::make('shortage_quantity')
                    ->label('Дефицит')
                    ->getStateUsing(fn (Inventory $record) => $record->shortageQuantity())
                    ->formatStateUsing(fn ($state, Inventory $record) => $state . ($record->product?->isWeighted() ? ' г' : ' шт.'))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->badge()
                    ->alignCenter(),

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
                        $query->whereRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' > 0')
                    ),
                
                Filter::make('out_of_stock')
                    ->label('Нет в наличии')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' <= 0')
                    ),

                Filter::make('shortage')
                    ->label('Есть дефицит')
                    ->query(fn (Builder $query): Builder => $query->whereRaw(
                        '(reserved_online_quantity + reserved_seller_quantity) > quantity'
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust_quantity')
                    ->label('Корректировать остаток')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->form([
                        Forms\Components\TextInput::make('new_quantity')
                            ->label('Новый физический остаток')
                            ->integer()
                            ->minValue(0)
                            ->default(fn (Inventory $record) => $record->quantity)
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Причина корректировки')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (Inventory $record, array $data): void {
                        app(\App\Services\WarehouseService::class)->adjustQuantity(
                            $record,
                            (int) $data['new_quantity'],
                            $data['reason'],
                            auth()->id()
                        );

                        Notification::make()
                            ->title('Остаток скорректирован')
                            ->success()
                            ->send();
                    }),
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
