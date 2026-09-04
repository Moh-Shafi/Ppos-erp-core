<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountPreset extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'name',
        'type',
        'value',
        'is_active',
        'sort_order',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
