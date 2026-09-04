<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * List payments.
     */
    public function index(Request $request)
    {
        $query = Payment::query();

        if ($request->filled('method')) {
            $query->where('payment_method', $request->get('method'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('store_id')) {
            $query->whereHas('sale', function ($q) use ($request) {
                $q->where('store_id', $request->get('store_id'));
            });
        }

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->get('sale_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->get('date_to'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $payments = $query->with('sale')->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($payments);
    }

    /**
     * Show payment details.
     */
    public function show(int $id)
    {
        $payment = Payment::with('sale')->findOrFail($id);

        return response()->json(['data' => $payment]);
    }

    /**
     * Payment summary.
     */
    public function summary(Request $request)
    {
        $query = Payment::query();

        if ($request->filled('store_id')) {
            $query->whereHas('sale', function ($q) use ($request) {
                $q->where('store_id', $request->get('store_id'));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->get('date_to'));
        }

        $stats = $query->clone();

        $total = (float) $stats->sum('amount');
        $success = (float) $query->clone()->where('status', 'success')->sum('amount');
        $pending = (float) $query->clone()->where('status', 'pending')->sum('amount');
        $failed = (float) $query->clone()->where('status', 'failed')->sum('amount');
        $refunded = (float) $query->clone()->where('status', 'refunded')->sum('amount');
        $fees = (float) $query->clone()->where('status', 'success')->sum('platform_fee');
        $net = (float) $query->clone()->where('status', 'success')->sum('net_amount');

        $byMethod = $query->clone()
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_method')
            ->get();

        return response()->json(['data' => [
            'total_payments' => $query->count(),
            'total_amount' => $total,
            'success_amount' => $success,
            'pending_amount' => $pending,
            'failed_amount' => $failed,
            'refunded_amount' => $refunded,
            'total_fees' => $fees,
            'net_settled' => $net,
            'by_method' => $byMethod,
        ]]);
    }
}
