<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerLoyaltyPoints extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_loyalty_points';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'points_balance',
        'total_earned',
        'total_redeemed',
    ];

    protected $casts = [
        'points_balance' => 'integer',
        'total_earned' => 'integer',
        'total_redeemed' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyTransaction::class, 'customer_id', 'customer_id');
    }
}
