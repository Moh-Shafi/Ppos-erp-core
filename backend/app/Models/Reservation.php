<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'store_id', 'customer_id', 'table_id',
        'reservation_date', 'start_time', 'end_time',
        'party_size', 'status', 'notes',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'reservation_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }
}
