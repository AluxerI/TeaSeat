<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopProductsTable;
use App\Filament\Widgets\LowStockTable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $title = 'Статистика';
    protected static ?string $navigationLabel = 'Статистика';

}