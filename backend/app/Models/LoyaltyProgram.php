<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'points_per_currency', 'currency_per_point', 'is_active'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'points_per_currency' => 'decimal:4',
        'currency_per_point' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tiers(): HasMany
    {
        return $this->hasMany(LoyaltyTier::class);
    }
}
