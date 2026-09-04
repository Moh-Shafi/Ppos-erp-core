<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSchedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'day_of_week', 'start_time', 'end_time',
        'is_available', 'effective_from', 'effective_until',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'is_available' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
