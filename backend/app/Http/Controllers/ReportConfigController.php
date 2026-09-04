<?php

namespace App\Http\Controllers;

use App\Models\ReportConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $configs = ReportConfig::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $configs]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'report_id' => 'required|string',
            'filters' => 'required|array',
        ]);

        $config = ReportConfig::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'report_id' => $validated['report_id'],
            'filters' => $validated['filters'],
        ]);

        return response()->json(['data' => $config], 201);
    }

    public function show(ReportConfig $report_config): JsonResponse
    {
        $this->assertOwnership($report_config);

        return response()->json(['data' => $report_config]);
    }

    public function update(Request $request, ReportConfig $report_config): JsonResponse
    {
        $this->assertOwnership($report_config);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'report_id' => 'sometimes|string',
            'filters' => 'sometimes|array',
        ]);

        $report_config->update($validated);

        return response()->json(['data' => $report_config]);
    }

    public function destroy(ReportConfig $report_config): JsonResponse
    {
        $this->assertOwnership($report_config);

        $report_config->delete();

        return response()->json(null, 204);
    }

    protected function assertOwnership(ReportConfig $report_config): void
    {
        $user = Auth::user();

        if ($report_config->tenant_id !== $user->tenant_id || $report_config->user_id !== $user->id) {
            abort(403);
        }
    }
}
