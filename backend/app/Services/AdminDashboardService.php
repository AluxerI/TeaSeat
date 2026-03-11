<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminDashboardService
{
    public function getStats(): array
    {
        return Cache::remember('admin.dashboard.stats', 300, function () {
            return [
                'products' => $this->getProductStats(),
                'orders' => $this->getOrderStats(),
                'inventory' => $this->getInventoryStats(),
                'customers' => $this->getCustomerStats(),
            ];
        });
    }

    private function getProductStats(): array
    {
        return [
            'total' => DB::table('products')->count(),
            'in_stock' => DB::table('products')->where('is_available', true)->count(),
            'out_of_stock' => DB::table('products')->where('is_available', false)->count(),
            'top_selling' => DB::table('products')
                ->select('id', 'name', 'sold_count', 'price')
                ->orderBy('sold_count', 'desc')
                ->limit(10)
                ->get(),
            'top_rated' => DB::table('products')
                ->join('reviews', 'products.id', '=', 'reviews.product_id')
                ->select(
                    'products.id', 
                    'products.name', 
                    DB::raw('AVG(reviews.rating) as avg_rating'), 
                    DB::raw('COUNT(reviews.product_id) as reviews_count')
                )
                ->groupBy('products.id', 'products.name')
                ->having(DB::raw('COUNT(reviews.product_id)'), '>', 0)  // ← ИСПРАВЛЕНО
                ->orderBy('avg_rating', 'desc')
                ->limit(10)
                ->get(),
        ];
    }

    private function getOrderStats(): array
    {
        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        $stats = [];
        
        foreach ($statuses as $status) {
            $stats[$status] = DB::table('orders')
                ->where('status', $status)
                ->count();
        }

        $stats['total_revenue'] = DB::table('orders')
            ->whereIn('status', ['delivered', 'shipped', 'processing'])
            ->sum('final_total');

        $stats['revenue_today'] = DB::table('orders')
            ->whereIn('status', ['delivered', 'shipped', 'processing'])
            ->whereDate('created_at', today())
            ->sum('final_total');

        $stats['revenue_month'] = DB::table('orders')
            ->whereIn('status', ['delivered', 'shipped', 'processing'])
            ->whereMonth('created_at', now()->month)
            ->sum('final_total');

        return $stats;
    }

    private function getInventoryStats(): array
    {
        return [
            'low_stock' => DB::table('inventories')
                ->join('products', 'inventories.product_id', '=', 'products.id')
                ->select(
                    'products.id', 
                    'products.name', 
                    DB::raw('SUM(inventories.quantity) as total')
                )
                ->groupBy('products.id', 'products.name')
                ->having(DB::raw('SUM(inventories.quantity)'), '<', 10)  // ← ИСПРАВЛЕНО
                ->having(DB::raw('SUM(inventories.quantity)'), '>', 0)   // ← ИСПРАВЛЕНО
                ->orderBy('total', 'asc')
                ->limit(20)
                ->get(),
            'out_of_stock' => DB::table('products')
                ->where('is_available', false)
                ->count(),
            'total_items' => DB::table('inventories')->sum('quantity'),
        ];
    }

    private function getCustomerStats(): array
    {
        return [
            'total' => DB::table('users')->count(),
            'active_today' => DB::table('sessions')->distinct('user_id')->count('user_id'),
            'new_this_month' => DB::table('users')
                ->whereMonth('created_at', now()->month)
                ->count(),
            'top_customers' => DB::table('orders')
                ->join('users', 'orders.user_id', '=', 'users.id')
                ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(orders.id) as orders_count'), DB::raw('SUM(orders.final_total) as total_spent'))
                ->where('orders.status', 'delivered')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderBy('total_spent', 'desc')
                ->limit(10)
                ->get(),
        ];
    }
}