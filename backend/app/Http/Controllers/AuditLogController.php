<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    public function __construct(protected AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['entity_type', 'user_id', 'action', 'date_from', 'date_to', 'route', 'method', 'per_page']);

        $logs = $this->auditService->listLogs(Auth::user()->tenant_id, $filters);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        $filters = $request->only(['entity_type', 'user_id', 'action', 'date_from', 'date_to', 'route', 'method']);
        $filters['per_page'] = 10000;

        $logs = $this->auditService->listLogs(Auth::user()->tenant_id, $filters);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-logs-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'tenant_id', 'user_id', 'action', 'entity_type', 'entity_id', 'ip_address', 'route', 'method', 'created_at']);

            foreach ($logs->items() as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->tenant_id,
                    $log->user_id,
                    $log->action,
                    $log->entity_type,
                    $log->entity_id,
                    $log->ip_address,
                    $log->route,
                    $log->method,
                    $log->created_at?->toIso8601String(),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
