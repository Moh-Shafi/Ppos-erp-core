<?php

namespace App\Http\Controllers;

use App\Models\DashboardWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardWidgetController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $widgets = DashboardWidget::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->orderBy('position')
            ->get();

        return response()->json(['data' => $widgets]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'type' => 'required|in:kpi,report',
            'kpi_id' => 'required_if:type,kpi|nullable|string',
            'report_id' => 'required_if:type,report|nullable|string',
            'filters' => 'sometimes|array',
            'position' => 'sometimes|array',
        ]);

        $widget = DashboardWidget::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'kpi_id' => $validated['kpi_id'] ?? null,
            'report_id' => $validated['report_id'] ?? null,
            'filters' => $validated['filters'] ?? [],
            'position' => $validated['position'] ?? [],
        ]);

        return response()->json(['data' => $widget], 201);
    }

    public function show(DashboardWidget $widget): JsonResponse
    {
        $this->assertOwnership($widget);

        return response()->json(['data' => $widget]);
    }

    public function update(Request $request, DashboardWidget $widget): JsonResponse
    {
        $this->assertOwnership($widget);

        $validated = $request->validate([
            'type' => 'sometimes|in:kpi,report',
            'kpi_id' => 'required_if:type,kpi|nullable|string',
            'report_id' => 'required_if:type,report|nullable|string',
            'filters' => 'sometimes|array',
            'position' => 'sometimes|array',
        ]);

        $widget->update($validated);

        return response()->json(['data' => $widget]);
    }

    public function destroy(DashboardWidget $widget): JsonResponse
    {
        $this->assertOwnership($widget);

        $widget->delete();

        return response()->json(null, 204);
    }

    protected function assertOwnership(DashboardWidget $widget): void
    {
        $user = Auth::user();

        if ($widget->tenant_id !== $user->tenant_id || $widget->user_id !== $user->id) {
            abort(403);
        }
    }
}
