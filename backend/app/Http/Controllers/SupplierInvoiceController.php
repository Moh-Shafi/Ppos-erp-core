<?php

namespace App\Http\Controllers;

use App\Models\SupplierInvoice;
use App\Services\InvoiceMatchingService;
use Illuminate\Http\Request;

class SupplierInvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceMatchingService $invoiceService,
    ) {}

    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $filters = $request->only(['status', 'supplier_id']);

        return response()->json($this->invoiceService->list($filters, $perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:100',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'purchase_id' => 'nullable|integer|exists:purchases,id',
            'grn_id' => 'nullable|integer|exists:goods_receipt_notes,id',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
        ]);

        try {
            $invoice = $this->invoiceService->create($validated);
            return response()->json($invoice, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(int $id)
    {
        return response()->json($this->invoiceService->find($id));
    }

    public function match(int $id)
    {
        $invoice = SupplierInvoice::findOrFail($id);

        try {
            return response()->json($this->invoiceService->match($invoice));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(int $id)
    {
        $invoice = SupplierInvoice::findOrFail($id);

        try {
            return response()->json($this->invoiceService->approve($invoice));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, int $id)
    {
        $invoice = SupplierInvoice::findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        try {
            return response()->json($this->invoiceService->reject($invoice, $validated['rejection_reason']));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
