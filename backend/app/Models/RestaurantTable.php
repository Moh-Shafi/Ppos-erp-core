<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    use BelongsToTenant;

    protected $table = 'tables';

    protected $fillable = ['store_id', 'table_area_id', 'name', 'code', 'capacity', 'status', 'qr_code'];

    protected $hidden = ['tenant_id'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(TableArea::class, 'table_area_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'table_id');
    }

    public function currentSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'id', 'table_id')->where('status', 'completed')->latest();
    }
}
