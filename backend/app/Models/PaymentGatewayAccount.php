<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayAccount extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'gateway',
        'gateway_account_id',
        'status',
        'kyc_status',
        'capabilities',
        'webhook_url',
        'metadata',
        'activated_at',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'metadata' => 'array',
        'activated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
