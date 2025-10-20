<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_order_id',
        'product_id',
        'customer_order_id',
        'quantity',
        'unit_cost'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2'
    ];

    public function supplierOrder()
    {
        return $this->belongsTo(SupplierOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customerOrder()
    {
        return $this->belongsTo(Order::class, 'customer_order_id');
    }
}