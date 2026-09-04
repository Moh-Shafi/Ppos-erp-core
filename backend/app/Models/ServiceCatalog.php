<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCatalog extends Model
{
    use BelongsToTenant;

    protected $table = 'service_catalog';

    protected $fillable = [
        'product_id', 'duration_minutes',
        'is_recurring', 'recurring_interval', 'buffer_time_minutes',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'is_recurring' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
