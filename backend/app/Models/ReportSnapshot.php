<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportSnapshot extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['report_id', 'filter_hash', 'filters', 'result', 'version', 'expires_at'];

    protected $casts = [
        'filters' => 'array',
        'result' => 'array',
        'expires_at' => 'datetime',
    ];
}
