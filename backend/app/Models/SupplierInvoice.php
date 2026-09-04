<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoice extends Model
{
    use BelongsToTenant;

    protected $table = 'supplier_invoices';

    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'supplier_id',
        'purchase_id',
        'grn_id',
        'status',
        'subtotal',
        'tax',
        'total',
        'invoice_date',
        'due_date',
        'match_result',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'match_result' => 'array',
        'approved_at' => 'datetime',
        'approved_by' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
