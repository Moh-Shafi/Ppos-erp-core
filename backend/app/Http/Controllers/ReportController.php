<?php

namespace App\Http\Controllers;

use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\DashboardService;
use App\Services\Reports\Exceptions\UnregisteredReportException;
use App\Services\Reports\ExportService;
use App\Services\Reports\KpiRegistry;
use App\Services\Reports\ReportContext;
use App\Services\Reports\ReportEngine;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ReportController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        protected ReportEngine $engine,
        protected DashboardService $dashboardService,
        protected ExportService $exportService,
    ) {
    }

    public function dashboard(Request $request)
    {
        $this->authorize('reports.view');

        $user = Auth::user();
        $filters = $request->only(['date_from', 'date_to']);

        return response()->json([
            'date_range' => [
                'from' => $filters['date_from'] ?? null,
                'to' => $filters['date_to'] ?? null,
            ],
            'widgets' => $this->dashboardService->load($user, $filters),
        ]);
    }

    public function kpis(): JsonResponse
    {
        $this->authorize('reports.view');

        return response()->json([
            'data' => array_keys(KpiRegistry::all()),
        ]);
    }

    public function report(Request $request, string $reportId): JsonResponse
    {
        $user = Auth::user();
        $scope = AuthorizedStoreScope::forUser($user);
        $ctx = new ReportContext(user: $user, filters: $request->all());

        try {
            $definition = $this->engine->resolve($reportId);
            $this->authorize($definition->requiredPermission());
            $result = $this->engine->run($reportId, $ctx, $scope);
        } catch (UnregisteredReportException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray());
    }

    public function drillDown(Request $request, string $reportId): JsonResponse
    {
        $user = Auth::user();
        $scope = AuthorizedStoreScope::forUser($user);
        $ctx = new ReportContext(user: $user, filters: $request->all());

        try {
            $definition = $this->engine->resolve($reportId);
            $this->authorize($definition->requiredPermission());
            $result = $this->engine->run($reportId, $ctx, $scope);
        } catch (UnregisteredReportException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray());
    }

    public function export(Request $request): Response|JsonResponse
    {
        $request->validate([
            'report_id' => 'required|string',
            'filters' => 'sometimes|array',
            'format' => 'required|in:csv,xlsx,pdf',
        ]);

        $user = Auth::user();
        $scope = AuthorizedStoreScope::forUser($user);
        $ctx = new ReportContext(user: $user, filters: $request->input('filters', []));

        try {
            $definition = $this->engine->resolve($request->input('report_id'));
            $this->authorize($definition->requiredPermission());
            $result = $this->engine->run($request->input('report_id'), $ctx, $scope);
        } catch (UnregisteredReportException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->exportService->download($result, $request->input('format'));
    }
}
