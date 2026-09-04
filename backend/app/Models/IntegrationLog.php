<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tenant_integration_id', 'direction', 'method', 'url',
        'request_headers', 'request_body', 'response_status', 'response_body',
        'latency_ms', 'error_message', 'idempotency_key',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'response_status' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(TenantIntegration::class, 'tenant_integration_id');
    }
}
