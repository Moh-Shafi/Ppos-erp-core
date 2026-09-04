<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'config_schema', 'is_active', 'is_system',
    ];

    protected $casts = [
        'config_schema' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function tenantIntegrations()
    {
        return $this->hasMany(TenantIntegration::class);
    }
}
