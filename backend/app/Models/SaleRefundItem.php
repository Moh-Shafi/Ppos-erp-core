<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleRefundItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_refund_id',
        'sale_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'refund_amount',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function saleRefund(): BelongsTo
    {
        return $this->belongsTo(SaleRefund::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
