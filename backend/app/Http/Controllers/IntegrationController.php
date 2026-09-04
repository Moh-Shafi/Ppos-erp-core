<?php

namespace App\Http\Controllers;

use App\Services\IntegrationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntegrationController extends Controller
{
    public function __construct(
        protected IntegrationService $integrationService
    ) {}

    public function providers()
    {
        return response()->json([
            'data' => $this->integrationService->listProviders(),
        ]);
    }

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $integrations = $this->integrationService->listIntegrations($tenantId, $request->only(['status', 'per_page']));
        return response()->json($integrations);
    }

    public function show(Request $request, int $id)
    {
        $integration = $this->integrationService->getIntegration($request->user()->tenant_id, $id);
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }
        return response()->json(['data' => $integration]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider_slug' => 'required|string',
            'name' => 'required|string|max:100',
            'config' => 'nullable|array',
            'credentials' => 'nullable|array',
        ]);

        try {
            $integration = $this->integrationService->create(
                $request->user()->tenant_id,
                $validated['provider_slug'],
                $validated['name'],
                $validated['config'] ?? [],
                $validated['credentials'] ?? [],
            );
            return response()->json(['data' => $integration], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, int $id)
    {
        $integration = $this->integrationService->getIntegration($request->user()->tenant_id, $id);
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'config' => 'sometimes|array',
        ]);

        $updated = $this->integrationService->updateConfig($integration, $validated['config'] ?? $integration->config ?? []);
        if (isset($validated['name'])) {
            $updated->update(['name' => $validated['name']]);
        }

        return response()->json(['data' => $updated->fresh()]);
    }

    public function updateCredentials(Request $request, int $id)
    {
        $integration = $this->integrationService->getIntegration($request->user()->tenant_id, $id);
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $validated = $request->validate([
            'credentials' => 'required|array',
        ]);

        $updated = $this->integrationService->updateCredentials($integration, $validated['credentials']);
        return response()->json(['data' => $updated]);
    }

    public function testConnection(Request $request, int $id)
    {
        $integration = $this->integrationService->getIntegration($request->user()->tenant_id, $id);
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $result = $this->integrationService->testConnection($integration);
        return response()->json($result);
    }

    public function activate(Request $request, int $id)
    {
        $integration = $this->integrationService->getIntegration($request->user()->tenant_id, $id);
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        return response()->json(['data' => $this->integrationService->activate($integration)]);
    }

    public function deactivate(Request $request, int $id)
    {
        $integration = $this->integrationService->getIntegration($request->user()->tenant_id, $id);
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        return response()->json(['data' => $this->integrationService->deactivate($integration)]);
    }

    public function destroy(Request $request, int $id)
    {
        $integration = $this->integrationService->getIntegration($request->user()->tenant_id, $id);
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $this->integrationService->delete($integration);
        return response()->json(null, 204);
    }

    public function logs(Request $request, int $id)
    {
        $integration = $this->integrationService->getIntegration($request->user()->tenant_id, $id);
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        $logs = $this->integrationService->getLogs($request->user()->tenant_id, $id, $request->only(['direction', 'date_from', 'date_to', 'per_page']));
        return response()->json($logs);
    }

    public function health(Request $request)
    {
        $health = $this->integrationService->getHealth($request->user()->tenant_id);
        return response()->json(['data' => $health]);
    }
}
