<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTier extends Model
{
    protected $fillable = ['loyalty_program_id', 'name', 'min_points', 'discount_percentage'];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }
}
