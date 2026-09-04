<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillSplitItem extends Model
{
    protected $fillable = ['bill_split_id', 'sale_item_id', 'customer_id', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function billSplit(): BelongsTo
    {
        return $this->belongsTo(BillSplit::class, 'bill_split_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
