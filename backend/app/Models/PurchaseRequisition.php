<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequisition extends Model
{
    use BelongsToTenant;

    protected $table = 'purchase_requisitions';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'request_number',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'note',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'requested_by' => 'integer',
        'approved_by' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class, 'requisition_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'requisition_id');
    }
}
