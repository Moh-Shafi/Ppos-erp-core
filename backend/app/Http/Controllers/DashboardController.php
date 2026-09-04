<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $storeId = Request::header('X-Store-Id');

        $saleQuery = Sale::where('tenant_id', $tenantId)
            ->where('status', 'completed');

        if ($storeId) {
            $saleQuery->where('store_id', $storeId);
        }

        // ===== Basic stats =====
        $todayStats = (clone $saleQuery)->whereDate('created_at', today())->get();
        $todayRevenue = $todayStats->sum('total');
        $todayCount = $todayStats->count();

        $yesterdayStats = (clone $saleQuery)->whereDate('created_at', today()->subDay())->get();
        $yesterdayRevenue = $yesterdayStats->sum('total');
        $yesterdayCount = $yesterdayStats->count();

        $totalProducts = Product::where('tenant_id', $tenantId)->count();
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();

        // Revenue trend (last 7 days sparkline)
        $revenueTrend = [];
        for ($i = 9; $i >= 0; $i--) {
            $d = today()->subDays($i);
            $rev = (clone $saleQuery)->whereDate('created_at', $d)->sum('total');
            $revenueTrend[] = (float) $rev;
        }

        $countTrend = [];
        for ($i = 9; $i >= 0; $i--) {
            $d = today()->subDays($i);
            $cnt = (clone $saleQuery)->whereDate('created_at', $d)->count();
            $countTrend[] = $cnt;
        }

        // Trend percentages
        $revenueTrendPct = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($todayRevenue > 0 ? 100 : 0);
        $countTrendPct = $yesterdayCount > 0
            ? round((($todayCount - $yesterdayCount) / $yesterdayCount) * 100, 1)
            : ($todayCount > 0 ? 100 : 0);

        // Recent sales
        $recentSales = (clone $saleQuery)
            ->with(['store:id,name', 'cashier:id,name'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'sale_number', 'total', 'status', 'store_id', 'cashier_id', 'created_at']);

        // Low stock
        $lowStockQuery = Inventory::where('tenant_id', $tenantId)
            ->with('product:id,name,sku')
            ->where('quantity', '<=', 10)
            ->orderBy('quantity');

        if ($storeId) {
            $lowStockQuery->where('store_id', $storeId);
        }

        $lowStock = (clone $lowStockQuery)->limit(5)->get();

        // ===== Weekly chart (last 7 days) =====
        $weeklyData = [];
        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        for ($i = 6; $i >= 0; $i--) {
            $d = today()->subDays($i);
            $rev = (clone $saleQuery)->whereDate('created_at', $d)->sum('total');
            $cnt = (clone $saleQuery)->whereDate('created_at', $d)->count();
            $weeklyData[] = [
                'day' => $dayNames[(int) $d->format('w')],
                'date' => $d->format('Y-m-d'),
                'revenue' => (float) $rev,
                'count' => $cnt,
            ];
        }

        // Calculate max revenue for percentage
        $maxRevenue = collect($weeklyData)->max('revenue') ?: 1;

        // ===== Payment methods breakdown =====
        $paymentQuery = Payment::where('tenant_id', $tenantId)
            ->where('status', 'success')
            ->whereDate('created_at', today());

        if ($storeId) {
            $paymentQuery->whereHas('sale', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        $paymentBreakdown = $paymentQuery
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        $paymentTotal = $paymentBreakdown->sum('total') ?: 1;
        $paymentLabels = [
            'cash' => 'Tunai',
            'qris' => 'QRIS',
            'card' => 'Kartu',
            'bank_transfer' => 'Transfer',
        ];

        $paymentMethods = $paymentBreakdown->map(function ($p) use ($paymentLabels, $paymentTotal) {
            return [
                'method' => $p->payment_method,
                'label' => $paymentLabels[$p->payment_method] ?? ucfirst($p->payment_method),
                'total' => (float) $p->total,
                'count' => $p->count,
                'percentage' => round(($p->total / $paymentTotal) * 100, 1),
            ];
        })->values();

        // If no payments today, get all-time breakdown
        if ($paymentMethods->isEmpty()) {
            $allPaymentQuery = Payment::where('tenant_id', $tenantId)->where('status', 'success');
            if ($storeId) {
                $allPaymentQuery->whereHas('sale', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                });
            }
            $allBreakdown = $allPaymentQuery
                ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('payment_method')
                ->get();
            $allTotal = $allBreakdown->sum('total') ?: 1;
            $paymentMethods = $allBreakdown->map(function ($p) use ($paymentLabels, $allTotal) {
                return [
                    'method' => $p->payment_method,
                    'label' => $paymentLabels[$p->payment_method] ?? ucfirst($p->payment_method),
                    'total' => (float) $p->total,
                    'count' => $p->count,
                    'percentage' => round(($p->total / $allTotal) * 100, 1),
                ];
            })->values();
        }

        // ===== Top products (last 7 days) =====
        $topProductsQuery = SaleItem::select(
            'product_id',
            'product_name',
            DB::raw('SUM(quantity) as total_sold'),
            DB::raw('SUM(total) as total_revenue')
        )
            ->whereHas('sale', function ($q) use ($tenantId, $storeId) {
                $q->where('tenant_id', $tenantId)->where('status', 'completed')
                    ->whereDate('created_at', '>=', today()->subDays(7));
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            })
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        $topProducts = $topProductsQuery->map(function ($p, $i) {
            return [
                'rank' => $i + 1,
                'product_id' => $p->product_id,
                'name' => $p->product_name,
                'sold' => (int) $p->total_sold,
                'revenue' => (float) $p->total_revenue,
            ];
        })->values();

        // ===== Sales target =====
        // Target = average of last 7 days revenue * 1.15 (15% growth target)
        $last7Revenue = collect($weeklyData)->sum('revenue');
        $dailyTarget = $last7Revenue > 0 ? round(($last7Revenue / 7) * 1.15) : 15000000;
        $targetProgress = $dailyTarget > 0 ? round(($todayRevenue / $dailyTarget) * 100) : 0;

        // ===== Recent activity (from audit logs) =====
        $activityQuery = AuditLog::where('tenant_id', $tenantId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(8);

        $activities = $activityQuery->get()->map(function ($log) {
            $actionLabels = [
                'login' => ['text' => 'Login berhasil', 'icon' => 'login', 'color' => 'blue'],
                'register' => ['text' => 'Registrasi tenant baru', 'icon' => 'register', 'color' => 'green'],
                'create' => ['text' => "Membuat {$log->entity_type}", 'icon' => 'create', 'color' => 'blue'],
                'update' => ['text' => "Memperbarui {$log->entity_type}", 'icon' => 'update', 'color' => 'yellow'],
                'delete' => ['text' => "Menghapus {$log->entity_type}", 'icon' => 'delete', 'color' => 'red'],
            ];

            $label = $actionLabels[$log->action] ?? ['text' => ucfirst($log->action) . " {$log->entity_type}", 'icon' => 'default', 'color' => 'gray'];

            return [
                'id' => $log->id,
                'text' => $label['text'],
                'icon' => $label['icon'],
                'color' => $label['color'],
                'user' => $log->user?->name ?? 'System',
                'time' => $log->created_at->diffForHumans(),
                'created_at' => $log->created_at->toIso8601String(),
            ];
        })->values();

        // If no audit logs, generate from recent sales
        if ($activities->isEmpty()) {
            $recentForActivity = (clone $saleQuery)
                ->with(['payments' => function ($q) {
                    $q->where('status', 'success')->limit(1);
                }])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            $activities = $recentForActivity->map(function ($sale) {
                $method = $sale->payments->first()?->payment_method ?? 'cash';
                $methodLabel = ['cash' => 'Tunai', 'qris' => 'QRIS', 'card' => 'Kartu', 'bank_transfer' => 'Transfer'][$method] ?? ucfirst($method);
                return [
                    'id' => $sale->id,
                    'text' => "Pembayaran {$methodLabel} {$sale->sale_number} — Rp " . number_format((float) $sale->total, 0, ',', '.'),
                    'icon' => 'payment',
                    'color' => 'green',
                    'user' => $sale->cashier?->name ?? 'System',
                    'time' => $sale->created_at->diffForHumans(),
                    'created_at' => $sale->created_at->toIso8601String(),
                ];
            })->values();
        }

        return response()->json([
            'stats' => [
                'today_revenue' => (float) $todayRevenue,
                'today_sales_count' => $todayCount,
                'total_products' => $totalProducts,
                'total_customers' => $totalCustomers,
                'yesterday_revenue' => (float) $yesterdayRevenue,
                'yesterday_count' => $yesterdayCount,
                'revenue_trend_pct' => $revenueTrendPct,
                'count_trend_pct' => $countTrendPct,
                'revenue_trend' => $revenueTrend,
                'count_trend' => $countTrend,
            ],
            'recent_sales' => $recentSales,
            'low_stock' => $lowStock,
            'weekly_data' => $weeklyData,
            'weekly_max' => (float) $maxRevenue,
            'payment_methods' => $paymentMethods,
            'top_products' => $topProducts,
            'sales_target' => [
                'target' => (float) $dailyTarget,
                'current' => (float) $todayRevenue,
                'percentage' => min($targetProgress, 100),
                'remaining' => max(0, $dailyTarget - $todayRevenue),
            ],
            'activities' => $activities,
        ]);
    }
}
