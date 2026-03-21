<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Services\AdminDashboardService;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $service = app(AdminDashboardService::class);
        $stats = $service->getStats();

        return [
            Stat::make('Товары', $stats['products']['total'])
                ->description($stats['products']['in_stock'] . ' в наличии')
                ->descriptionIcon('heroicon-m-cube')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),
            
            Stat::make('Заказы', array_sum(array_diff_key($stats['orders'], ['total_revenue' => 0, 'revenue_today' => 0, 'revenue_month' => 0])))
                ->description($stats['orders']['pending'] . ' ожидают')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),
            
            Stat::make('Выручка', number_format($stats['orders']['total_revenue'], 0) . ' ₽')
                ->description('Сегодня: ' . number_format($stats['orders']['revenue_today'], 0) . ' ₽')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            
            Stat::make('Клиенты', $stats['customers']['total'])
                ->description($stats['customers']['active_today'] . ' активны сегодня')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
            
            Stat::make('Низкий остаток', $stats['inventory']['low_stock']->count())
                ->description($stats['inventory']['total_items'] . ' единиц всего')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}