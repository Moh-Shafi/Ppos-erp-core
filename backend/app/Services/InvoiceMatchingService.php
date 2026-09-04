<?php

namespace App\Services;

use App\Models\SupplierInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceMatchingService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function list(array $filters, int $perPage = 20)
    {
        $query = SupplierInvoice::with(['supplier', 'purchase', 'grn', 'approvedBy:id,name,email']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): SupplierInvoice
    {
        return SupplierInvoice::with(['supplier', 'purchase', 'grn', 'approvedBy:id,name,email'])
            ->findOrFail($id);
    }

    public function create(array $data): SupplierInvoice
    {
        $tenantId = Auth::user()->tenant_id;

        return DB::transaction(function () use ($data, $tenantId) {
            $invoice = new SupplierInvoice;
            $invoice->tenant_id = $tenantId;
            $invoice->invoice_number = $data['invoice_number'];
            $invoice->supplier_id = $data['supplier_id'];
            $invoice->purchase_id = $data['purchase_id'] ?? null;
            $invoice->grn_id = $data['grn_id'] ?? null;
            $invoice->status = 'pending';
            $invoice->subtotal = $data['subtotal'];
            $invoice->tax = $data['tax'] ?? 0;
            $invoice->total = $data['total'];
            $invoice->invoice_date = $data['invoice_date'];
            $invoice->due_date = $data['due_date'] ?? null;
            $invoice->save();

            $this->auditService->log('invoice.created', 'supplier_invoice', $invoice->id, null, $invoice->toArray(), tenantId: $tenantId);

            return $invoice->fresh(['supplier', 'purchase', 'grn']);
        });
    }

    public function match(SupplierInvoice $invoice): SupplierInvoice
    {
        if ($invoice->status !== 'pending') {
            throw new \DomainException('Only pending invoices can be matched');
        }

        $tenantId = $invoice->tenant_id;
        $tolerance = (float) $this->getTenantSetting($tenantId, 'invoice_match_tolerance', 5);

        $matchResult = [
            'quantity_match' => true,
            'price_match' => true,
            'total_match' => true,
            'details' => [],
        ];

        $poTotal = null;
        $grnTotal = null;
        $invoiceTotal = (float) $invoice->total;

        if ($invoice->purchase_id) {
            $purchase = $invoice->purchase;
            $purchase->load('items');
            $poTotal = (float) $purchase->total;

            $matchResult['details']['po_total'] = $poTotal;

            if ($invoice->grn_id) {
                $grn = $invoice->grn;
                $grn->load('items');
                $grnTotal = $grn->items->sum(function ($item) {
                    return $item->quantity_received * $item->unit_cost;
                });
                $matchResult['details']['grn_total'] = $grnTotal;

                $poQty = $purchase->items->sum('quantity');
                $grnQty = $grn->items->sum('quantity_received');
                $matchResult['details']['po_qty'] = $poQty;
                $matchResult['details']['grn_qty'] = $grnQty;

                if ($poQty > 0) {
                    $qtyVariance = abs($poQty - $grnQty) / $poQty * 100;
                    $matchResult['quantity_match'] = $qtyVariance <= $tolerance;
                }
            }
        }

        $matchResult['details']['invoice_total'] = $invoiceTotal;
        $matchResult['details']['tolerance_pct'] = $tolerance;

        if ($poTotal !== null) {
            $totalVariance = $poTotal > 0 ? abs($poTotal - $invoiceTotal) / $poTotal * 100 : 0;
            $matchResult['total_match'] = $totalVariance <= $tolerance;
            $matchResult['details']['variance_pct'] = round($totalVariance, 2);
        }

        if ($grnTotal !== null) {
            $grnVariance = $grnTotal > 0 ? abs($grnTotal - $invoiceTotal) / $grnTotal * 100 : 0;
            $matchResult['total_match'] = $matchResult['total_match'] && ($grnVariance <= $tolerance);
            $matchResult['details']['grn_variance_pct'] = round($grnVariance, 2);
        }

        $allMatched = $matchResult['quantity_match'] && $matchResult['price_match'] && $matchResult['total_match'];

        $invoice->status = $allMatched ? 'matched' : 'mismatched';
        $invoice->match_result = $matchResult;
        $invoice->save();

        $action = $allMatched ? 'invoice.matched' : 'invoice.mismatched';
        $this->auditService->log($action, 'supplier_invoice', $invoice->id, null, $matchResult, tenantId: $tenantId);

        return $invoice->fresh(['supplier', 'purchase', 'grn']);
    }

    public function approve(SupplierInvoice $invoice): SupplierInvoice
    {
        if (!in_array($invoice->status, ['matched', 'mismatched'])) {
            throw new \DomainException('Only matched or mismatched invoices can be approved');
        }

        $invoice->status = 'approved';
        $invoice->approved_by = Auth::id();
        $invoice->approved_at = now();
        $invoice->save();

        $this->auditService->log('invoice.approved', 'supplier_invoice', $invoice->id, null, [
            'approved_by' => Auth::id(),
        ], tenantId: $invoice->tenant_id);

        return $invoice->fresh(['supplier', 'purchase', 'grn']);
    }

    public function reject(SupplierInvoice $invoice, string $reason): SupplierInvoice
    {
        if (!in_array($invoice->status, ['matched', 'mismatched'])) {
            throw new \DomainException('Only matched or mismatched invoices can be rejected');
        }

        $invoice->status = 'rejected';
        $invoice->rejection_reason = $reason;
        $invoice->save();

        $this->auditService->log('invoice.rejected', 'supplier_invoice', $invoice->id, null, [
            'rejection_reason' => $reason,
        ], tenantId: $invoice->tenant_id);

        return $invoice->fresh(['supplier', 'purchase', 'grn']);
    }

    private function getTenantSetting(int $tenantId, string $key, mixed $default = null): mixed
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant || !isset($tenant->settings[$key])) {
            return $default;
        }
        return $tenant->settings[$key];
    }
}
