<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'description', 'is_core', 'dependencies', 'sort_order', 'icon'];

    protected $casts = [
        'is_core' => 'boolean',
        'dependencies' => 'array',
        'sort_order' => 'integer',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function businessTypeModules(): HasMany
    {
        return $this->hasMany(BusinessTypeModule::class);
    }

    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }
}
