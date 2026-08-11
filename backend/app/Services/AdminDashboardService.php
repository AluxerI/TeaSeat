<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminDashboardService
{
    public function getStats(?int $warehouseId = null): array
    {
        $cacheKey = 'admin.dashboard.stats' . ($warehouseId ? ".warehouse.{$warehouseId}" : '');
        
        return Cache::remember($cacheKey, 300, function () use ($warehouseId) {
            return [
                'products' => $this->getProductStats(),
                'orders' => $this->getOrderStats(),
                'inventory' => $this->getInventoryStats($warehouseId),
                'customers' => $this->getCustomerStats(),
                'low_stock' => $this->getLowStockProducts($warehouseId),
            ];
        });
    }

    // Изменяем visibility с private на public
    public function getLowStockProducts(?int $warehouseId = null)
    {
        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->join('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id')
            ->select(
                'products.id',
                'products.name',
                'products.price',
                'warehouses.name as warehouse_name',
                'warehouses.id as warehouse_id'
            )
            ->selectRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' as quantity')
            ->where('warehouses.is_active', true)
            ->where('warehouses.is_online_fulfillment_enabled', true)
            ->whereRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' > 0')
            ->whereRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' < 10')
            ->orderByRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' asc');

        if ($warehouseId) {
            $query->where('inventories.warehouse_id', $warehouseId);
        }

        return $query->limit(10)->get();
    }

    public function getWarehousesList(): array
    {
        return DB::table('warehouses')
            ->select('id', 'name', 'city')
            ->orderBy('city')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->id => "{$item->name} ({$item->city})"])
            ->toArray();
    }

    // Добавляем недостающий параметр в getInventoryStats
    private function getInventoryStats(?int $warehouseId = null): array
    {
        $inventoryQuery = Inventory::query()->onlineFulfillment();
        
        if ($warehouseId) {
            $inventoryQuery->where('warehouse_id', $warehouseId);
        }

        return [
            'low_stock' => $this->getLowStockProducts($warehouseId),
            'out_of_stock' => DB::table('products')
                ->where('is_available', false)
                ->count(),
            'total_items' => Inventory::sumOnlineAvailable($inventoryQuery),
        ];
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
                ->where('reviews.status', 'published')
                ->whereNotNull('reviews.order_product_id')
                ->select(
                    'products.id', 
                    'products.name', 
                    DB::raw('AVG(reviews.rating) as avg_rating'), 
                    DB::raw('COUNT(reviews.product_id) as reviews_count')
                )
                ->groupBy('products.id', 'products.name')
                ->having(DB::raw('COUNT(reviews.product_id)'), '>', 0)
                ->orderBy('avg_rating', 'desc')
                ->limit(10)
                ->get(),
        ];
    }

    private function getOrderStats(): array
    {
        $statuses = ['pending', 'confirmed', 'processing', 'ready_for_delivery', 'shipped', 'awaiting_receipt', 'delivered', 'cancelled'];
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
