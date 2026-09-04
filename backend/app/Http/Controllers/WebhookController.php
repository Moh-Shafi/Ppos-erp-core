<?php

namespace App\Http\Controllers;

use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    public function indexEndpoints(Request $request)
    {
        $endpoints = $this->webhookService->listEndpoints($request->user()->tenant_id, $request->only(['is_active', 'per_page']));
        return response()->json($endpoints);
    }

    public function showEndpoint(Request $request, int $id)
    {
        $endpoint = $this->webhookService->getEndpoint($request->user()->tenant_id, $id);
        if (!$endpoint) {
            return response()->json(['message' => 'Endpoint not found'], 404);
        }
        return response()->json(['data' => $endpoint]);
    }

    public function storeEndpoint(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'url' => 'required|string|url',
            'events' => 'required|array',
            'events.*' => 'string',
            'description' => 'nullable|string',
        ]);

        try {
            $result = $this->webhookService->createEndpoint(
                $request->user()->tenant_id,
                $validated['name'],
                $validated['url'],
                $validated['events'],
                $validated['description'] ?? null,
            );
            return response()->json($result, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updateEndpoint(Request $request, int $id)
    {
        $endpoint = $this->webhookService->getEndpoint($request->user()->tenant_id, $id);
        if (!$endpoint) {
            return response()->json(['message' => 'Endpoint not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'url' => 'sometimes|string|url',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ]);

        try {
            $updated = $this->webhookService->updateEndpoint($endpoint, $validated);
            return response()->json(['data' => $updated]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroyEndpoint(Request $request, int $id)
    {
        $endpoint = $this->webhookService->getEndpoint($request->user()->tenant_id, $id);
        if (!$endpoint) {
            return response()->json(['message' => 'Endpoint not found'], 404);
        }

        $this->webhookService->deleteEndpoint($endpoint);
        return response()->json(null, 204);
    }

    public function testEndpoint(Request $request, int $id)
    {
        $endpoint = $this->webhookService->getEndpoint($request->user()->tenant_id, $id);
        if (!$endpoint) {
            return response()->json(['message' => 'Endpoint not found'], 404);
        }

        $result = $this->webhookService->sendTestPayload($endpoint);
        return response()->json($result);
    }

    public function subscribe(Request $request, int $id)
    {
        $endpoint = $this->webhookService->getEndpoint($request->user()->tenant_id, $id);
        if (!$endpoint) {
            return response()->json(['message' => 'Endpoint not found'], 404);
        }

        $validated = $request->validate(['event_type' => 'required|string']);

        try {
            $sub = $this->webhookService->subscribe($endpoint, $validated['event_type']);
            return response()->json(['data' => $sub], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function unsubscribe(Request $request, int $id, string $eventType)
    {
        $endpoint = $this->webhookService->getEndpoint($request->user()->tenant_id, $id);
        if (!$endpoint) {
            return response()->json(['message' => 'Endpoint not found'], 404);
        }

        $this->webhookService->unsubscribe($endpoint, $eventType);
        return response()->json(null, 204);
    }

    public function indexDeliveries(Request $request)
    {
        $deliveries = $this->webhookService->listDeliveries($request->user()->tenant_id, $request->only(['endpoint_id', 'event_type', 'status', 'date_from', 'date_to', 'per_page']));
        return response()->json($deliveries);
    }

    public function showDelivery(Request $request, int $id)
    {
        $delivery = $this->webhookService->getDelivery($request->user()->tenant_id, $id);
        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }
        return response()->json(['data' => $delivery]);
    }

    public function replayDelivery(Request $request, int $id)
    {
        $delivery = $this->webhookService->getDelivery($request->user()->tenant_id, $id);
        if (!$delivery) {
            return response()->json(['message' => 'Delivery not found'], 404);
        }

        try {
            $newDelivery = $this->webhookService->replayDelivery($delivery);
            return response()->json(['data' => $newDelivery], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function stats(Request $request)
    {
        $period = $request->get('period', '24h');
        $stats = $this->webhookService->getDeliveryStats($request->user()->tenant_id, $period);
        return response()->json($stats);
    }

    public function events()
    {
        return response()->json(['data' => $this->webhookService->listEvents()]);
    }
}
