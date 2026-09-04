<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name', 'description', 'type', 'value',
        'start_date', 'end_date', 'is_active',
        'usage_limit', 'usage_count', 'conditions',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'conditions' => 'array',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(PromotionRule::class);
    }
}
