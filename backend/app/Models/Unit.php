<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['name', 'symbol', 'is_base_unit', 'tenant_id'];

    protected $casts = [
        'is_base_unit' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function conversionsTo(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }

    public function productsAsBase(): HasMany
    {
        return $this->hasMany(Product::class, 'base_unit_id');
    }

    public function productsAsPurchase(): HasMany
    {
        return $this->hasMany(Product::class, 'purchase_unit_id');
    }
}
