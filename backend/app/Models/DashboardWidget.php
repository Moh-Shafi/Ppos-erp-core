<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['user_id', 'type', 'kpi_id', 'report_id', 'filters', 'position'];

    protected $casts = [
        'filters' => 'array',
        'position' => 'array',
    ];
}
