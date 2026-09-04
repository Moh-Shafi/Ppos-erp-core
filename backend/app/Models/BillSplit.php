<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillSplit extends Model
{
    use BelongsToTenant;

    protected $fillable = ['sale_id', 'split_type', 'total_amount', 'status'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillSplitItem::class, 'bill_split_id');
    }
}
