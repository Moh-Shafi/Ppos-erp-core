<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantIntegration extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'integration_provider_id', 'name', 'config',
        'encrypted_credentials', 'status', 'last_connected_at', 'last_error',
    ];

    protected $casts = [
        'config' => 'array',
        'last_connected_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_credentials',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(IntegrationProvider::class, 'integration_provider_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class, 'tenant_integration_id');
    }

    public function hasCredentials(): bool
    {
        return !empty($this->encrypted_credentials);
    }
}
