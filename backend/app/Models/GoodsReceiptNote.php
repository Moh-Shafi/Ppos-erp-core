<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptNote extends Model
{
    use BelongsToTenant;

    protected $table = 'goods_receipt_notes';

    protected $fillable = [
        'tenant_id',
        'grn_number',
        'purchase_id',
        'store_id',
        'supplier_id',
        'status',
        'received_by',
        'received_date',
        'note',
    ];

    protected $casts = [
        'received_by' => 'integer',
        'received_date' => 'date',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GrnItem::class, 'grn_id');
    }

    public function supplierInvoice(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class, 'grn_id');
    }
}
