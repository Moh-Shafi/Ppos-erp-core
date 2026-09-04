<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRating extends Model
{
    use BelongsToTenant;

    protected $table = 'supplier_ratings';

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'rating',
        'criteria',
        'note',
        'rated_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'rated_by' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }
}
