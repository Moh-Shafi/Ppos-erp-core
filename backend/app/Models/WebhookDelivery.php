<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'webhook_endpoint_id', 'event_type', 'event_id',
        'payload', 'signature', 'status', 'attempt_count', 'last_attempt_at',
        'request_headers', 'response_status', 'response_body', 'error_message',
        'latency_ms', 'original_delivery_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'request_headers' => 'array',
        'attempt_count' => 'integer',
        'response_status' => 'integer',
        'latency_ms' => 'integer',
        'last_attempt_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function originalDelivery(): BelongsTo
    {
        return $this->belongsTo(WebhookDelivery::class, 'original_delivery_id');
    }
}
