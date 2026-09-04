<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariantOptionValue extends Model
{
    use HasFactory;

    protected $fillable = ['option_id', 'value', 'sort_order'];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductVariantOption::class, 'option_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_values', 'option_value_id', 'variant_id');
    }
}
