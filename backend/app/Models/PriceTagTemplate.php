<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PriceTagTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'layout'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'layout' => 'array',
    ];
}
