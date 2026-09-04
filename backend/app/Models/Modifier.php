<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modifier extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'type', 'is_required'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_modifiers');
    }
}
