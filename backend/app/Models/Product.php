<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'barcode',
        'description',
        'cost_price',
        'selling_price',
        'unit',
        'image',
        'is_active',
        'is_service',
        'has_variants',
        'is_trackable',
        'min_stock',
        'base_unit_id',
        'purchase_unit_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_service' => 'boolean',
        'has_variants' => 'boolean',
        'is_trackable' => 'boolean',
        'min_stock' => 'integer',
    ];

    protected $attributes = [
        'has_variants' => false,
        'is_trackable' => true,
        'is_active' => true,
        'is_service' => false,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function variantOptions(): HasMany
    {
        return $this->hasMany(ProductVariantOption::class)->orderBy('sort_order');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function serviceCatalog(): HasOne
    {
        return $this->hasOne(ServiceCatalog::class);
    }

    public function modifiers()
    {
        return $this->belongsToMany(Modifier::class, 'product_modifiers');
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }
}
