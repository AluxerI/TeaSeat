<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Services\AdminDashboardService;

class TopProductsTable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $service = app(AdminDashboardService::class);
        $stats = $service->getStats();

        return $table
            ->query(
                \App\Models\Product::query()
                    ->whereIn('id', $stats['products']['top_selling']->pluck('id'))
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Товар')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sold_count')
                    ->label('Продано')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Остаток')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
            ])
            ->heading('Топ-10 товаров по продажам');
    }
}