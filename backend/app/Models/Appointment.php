<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'store_id', 'customer_id', 'user_id',
        'appointment_date', 'start_time', 'end_time',
        'status', 'notes', 'sale_id',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(AppointmentService::class, 'appointment_id');
    }
}
