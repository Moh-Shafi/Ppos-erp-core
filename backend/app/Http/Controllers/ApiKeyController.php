<?php

namespace App\Http\Controllers;

use App\Services\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiKeyController extends Controller
{
    public function __construct(
        protected ApiKeyService $apiKeyService
    ) {}

    public function index(Request $request)
    {
        $keys = $this->apiKeyService->listKeys($request->user()->tenant_id);
        return response()->json($keys);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|in:read,write,webhook',
        ]);

        $key = $this->apiKeyService->generate(
            $request->user()->tenant_id,
            $validated['name'],
            $validated['scopes'] ?? ['read'],
        );

        return response()->json($key, 201);
    }

    public function destroy(Request $request, int $id)
    {
        $keys = $this->apiKeyService->listKeys($request->user()->tenant_id);
        $key = $keys->getCollection()->firstWhere('id', $id);

        if (!$key) {
            return response()->json(['message' => 'API key not found'], 404);
        }

        $this->apiKeyService->revoke($key);
        return response()->json(null, 204);
    }

    public function rotate(Request $request, int $id)
    {
        $keys = $this->apiKeyService->listKeys($request->user()->tenant_id);
        $key = $keys->getCollection()->firstWhere('id', $id);

        if (!$key) {
            return response()->json(['message' => 'API key not found'], 404);
        }

        $newKey = $this->apiKeyService->rotate($key);
        return response()->json($newKey, 201);
    }
}
