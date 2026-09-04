<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    use HasFactory;

    protected $fillable = ['module_id', 'slug', 'name', 'description', 'is_default_enabled', 'is_owner_toggleable'];

    protected $casts = [
        'is_default_enabled' => 'boolean',
        'is_owner_toggleable' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function tenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class);
    }
}
