<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Services\AdminDashboardService;
use App\Models\Inventory;
use Filament\Tables\Filters\SelectFilter;

class LowStockTable extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        $service = app(AdminDashboardService::class);
        
        return $table
            ->query(\App\Models\Product::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Товар'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('low_stock_quantity')
                    ->label('Остаток')
                    ->badge()
                    ->color('danger')
                    ->getStateUsing(function ($record) {
                        return Inventory::sumOnlineAvailable(
                            $record->inventories()->onlineFulfillment()
                        );
                    }),
            ])
            ->filters([
                SelectFilter::make('warehouse')
                    ->label('Склад')
                    ->options($service->getWarehousesList())
                    ->placeholder('Все склады')
                    ->query(function ($query, $data) {
                        if (!$data['value']) {
                            return $query->whereHas('inventories', function ($q) {
                                $q->availableForOnline()
                                    ->whereRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' < 10');
                            });
                        }
                        return $query->whereHas('inventories', function ($q) use ($data) {
                            $q->where('warehouse_id', $data['value'])
                                ->availableForOnline()
                                ->whereRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' < 10');
                        });
                    }),
            ])
            ->heading('Товары с низким остатком (менее 10 шт.)')
            ->paginated(false);
    }
}
