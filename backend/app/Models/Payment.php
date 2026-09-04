<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'sale_id',
        'payment_method',
        'amount',
        'change_amount',
        'refund_amount',
        'refund_status',
        'payment_reference',
        'idempotency_key',
        'gateway_transaction_id',
        'gateway_status',
        'gateway_response',
        'settlement_amount',
        'platform_fee',
        'net_amount',
        'settled_at',
        'expires_at',
        'gateway_account_id',
        'status',
        'metadata',
        'payment_date',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array',
        'gateway_response' => 'array',
        'payment_date' => 'datetime',
        'settled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
