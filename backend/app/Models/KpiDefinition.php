<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiDefinition extends Model
{
    use HasFactory;

    protected $fillable = ['kpi_id', 'name', 'category', 'allowed_filters', 'value_format'];

    protected $casts = [
        'allowed_filters' => 'array',
    ];

    public function getIncrementing(): bool
    {
        return false;
    }

    protected $primaryKey = 'kpi_id';
    protected $keyType = 'string';
}
