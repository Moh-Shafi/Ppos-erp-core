<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $saleService,
        private PaymentService $paymentService,
        private RefundService $refundService,
    ) {}

    public function index(Request $request)
    {
        $query = Sale::with(['store', 'cashier', 'customer', 'items', 'payments']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->get('store_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('sale_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('sale_date', '<=', $request->get('date_to') . ' 23:59:59');
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $sales = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($sales);
    }

    public function show(int $id)
    {
        $sale = Sale::with(['store', 'cashier', 'customer', 'items.product', 'items.variant', 'payments', 'refunds.items', 'refunds.refundedBy'])
            ->findOrFail($id);

        return response()->json($sale);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|string|in:cash,qris,card,bank_transfer',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_reference' => 'nullable|string|max:255',
            'payments.*.idempotency_key' => 'nullable|string|max:255',
            'payments.*.metadata' => 'nullable|array',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $sale = $this->saleService->checkout($validated);
            return response()->json($sale, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(int $id)
    {
        $sale = Sale::findOrFail($id);

        try {
            $sale = $this->saleService->cancel($sale);
            return response()->json($sale);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function addPayment(Request $request, int $id)
    {
        $sale = Sale::findOrFail($id);

        $validated = $request->validate([
            'payment_method' => 'required|string|in:cash,qris,card,bank_transfer',
            'amount' => 'required|numeric|min:0.01',
            'payment_reference' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        try {
            $payment = $this->paymentService->addPayment($sale, $validated);
            return response()->json($payment, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function listPayments(int $id)
    {
        $sale = Sale::findOrFail($id);

        return response()->json($sale->payments);
    }

    public function listRefunds(int $id)
    {
        $sale = Sale::findOrFail($id);

        return response()->json($sale->refunds()->with(['items', 'refundedBy'])->get());
    }

    public function showRefund(int $saleId, int $refundId)
    {
        $sale = Sale::findOrFail($saleId);

        $refund = $sale->refunds()->with(['items', 'refundedBy'])->findOrFail($refundId);

        return response()->json($refund);
    }

    public function processRefund(Request $request, int $id)
    {
        $sale = Sale::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|string|in:full,partial',
            'reason' => 'nullable|string|max:2000',
            'items' => 'required_if:type,partial|array|min:1',
            'items.*.sale_item_id' => 'required_with:items|integer|exists:sale_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        try {
            if ($validated['type'] === 'full') {
                $refund = $this->refundService->fullRefund($sale, $validated['reason'] ?? null);
            } else {
                $refund = $this->refundService->partialRefund($sale, $validated['items'] ?? [], $validated['reason'] ?? null);
            }

            return response()->json($refund, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
