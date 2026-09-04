<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KotItem extends Model
{
    protected $table = 'kot_items';

    protected $fillable = [
        'kot_header_id', 'sale_item_id', 'product_id',
        'quantity', 'modifiers', 'notes', 'status',
    ];

    protected $casts = [
        'modifiers' => 'array',
    ];

    public function kotHeader(): BelongsTo
    {
        return $this->belongsTo(KotHeader::class, 'kot_header_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
