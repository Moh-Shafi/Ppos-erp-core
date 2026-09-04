<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldSale extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'store_id',
        'cashier_id',
        'customer_id',
        'cart_data',
        'hold_number',
        'status',
        'held_at',
        'recalled_at',
        'expires_at',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'cart_data' => 'array',
        'held_at' => 'datetime',
        'recalled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
