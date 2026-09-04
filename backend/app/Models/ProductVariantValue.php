<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductVariantValue extends Pivot
{
    protected $table = 'product_variant_values';

    public $timestamps = false;

    protected $fillable = ['variant_id', 'option_value_id'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(ProductVariantOptionValue::class, 'option_value_id');
    }
}
