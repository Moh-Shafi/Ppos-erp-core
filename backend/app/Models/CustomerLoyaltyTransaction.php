<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLoyaltyTransaction extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_loyalty_transactions';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'points',
        'type',
        'source',
        'reference_type',
        'reference_id',
        'balance_after',
        'note',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'reference_id' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
