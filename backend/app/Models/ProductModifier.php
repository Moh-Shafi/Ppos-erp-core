<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModifier extends Model
{
    protected $table = 'product_modifiers';

    protected $fillable = ['product_id', 'modifier_id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }
}
