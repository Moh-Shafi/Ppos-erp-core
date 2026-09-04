<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'module', 'payload_description', 'is_active',
    ];

    protected $casts = [
        'payload_description' => 'array',
        'is_active' => 'boolean',
    ];
}
