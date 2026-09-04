<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportConfig extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['user_id', 'name', 'report_id', 'filters'];

    protected $casts = [
        'filters' => 'array',
    ];
}
