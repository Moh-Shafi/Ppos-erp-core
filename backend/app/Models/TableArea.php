<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableArea extends Model
{
    use BelongsToTenant;

    protected $table = 'table_areas';

    protected $fillable = ['store_id', 'name', 'sort_order'];

    protected $hidden = ['tenant_id'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class, 'table_area_id');
    }
}
