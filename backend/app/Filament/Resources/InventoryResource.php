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
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Склад и поставщики';

    protected static ?string $navigationLabel = 'Остатки';

    protected static ?string $modelLabel = 'Остаток';

    protected static ?string $pluralModelLabel = 'Остатки';

    // Указываем, что ключ - составной (но Filament будет использовать product_id)
    public static function getRecordRouteKeyName(): ?string
    {
        return 'inventory_key';
    }

    // Переопределяем метод для разрешения модели по составному ключу
    public static function resolveRecordRouteBinding(int | string $key): ?Model
    {
        $query = parent::resolveRecordRouteBinding($key);
        
        if ($query) {
            return $query;
        }

        // Пытаемся найти по составному ключу
        $parts = explode('-', $key);
        if (count($parts) === 2) {
            return Inventory::where('product_id', $parts[0])
                ->where('warehouse_id', $parts[1])
                ->first();
        }

        return null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Информация об остатке')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('warehouse_id')
                                    ->label('Склад')
                                    ->relationship('warehouse', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn ($record) => $record !== null) // Нельзя менять склад при редактировании
                                    ->helperText(fn ($record) => $record ? 'Нельзя изменить склад' : null),
                                
                                Select::make('product_id')
                                    ->label('Товар')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn ($record) => $record !== null) // Нельзя менять товар при редактировании
                                    ->helperText(fn ($record) => $record ? 'Нельзя изменить товар' : null),
                                
                                TextInput::make('quantity')
                                    ->label('Количество')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->suffix('шт.'),
                                
                                DatePicker::make('last_restock_date')
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
                TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                
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
                    ->label('Количество')
                    ->sortable()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight(fn ($state) => $state > 0 ? 'bold' : 'normal'),
                
                TextColumn::make('last_restock_date')
                    ->label('Последняя поставка')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable()
                    ->color(function ($record) {
                        if (!$record->last_restock_date) {
                            return 'default';
                        }
                        
                        $thirtyDaysAgo = now()->subDays(30);
                        $lastRestockDate = \Carbon\Carbon::parse($record->last_restock_date);
                        
                        return $lastRestockDate->lt($thirtyDaysAgo) ? 'warning' : 'default';
                    }),
                
                TextColumn::make('created_at')
                    ->label('Запись создана')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse')
                    ->label('Склад')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('city')
                    ->label('Город')
                    ->relationship('warehouse', 'city')
                    ->searchable()
                    ->multiple(),
                
                SelectFilter::make('product')
                    ->label('Товар')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->optionsLimit(100),
                
                Filter::make('in_stock')
                    ->label('В наличии')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '>', 0)),
                
                Filter::make('out_of_stock')
                    ->label('Нет в наличии')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '<=', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Inventory $record): string => route('filament.admin.resources.inventories.view', [
                        'record' => $record->product_id . '-' . $record->warehouse_id
                    ])),
                
                Tables\Actions\EditAction::make()
                    ->url(fn (Inventory $record): string => route('filament.admin.resources.inventories.edit', [
                        'record' => $record->product_id . '-' . $record->warehouse_id
                    ])),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_restock_date', 'desc');
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
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'view' => Pages\ViewInventory::route('/{record}'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
    

    // Переопределяем глобальный запрос для правильной работы
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product', 'warehouse']);
    }
}