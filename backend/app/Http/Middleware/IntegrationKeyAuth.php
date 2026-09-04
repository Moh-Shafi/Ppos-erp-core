<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntegrationKeyAuth
{
    public function __construct(
        protected ApiKeyService $apiKeyService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Integration-Key');

        if (!$key) {
            return response()->json(['message' => 'Missing integration API key'], 401);
        }

        $apiKey = $this->apiKeyService->validate($key);

        if (!$apiKey) {
            return response()->json(['message' => 'Invalid or revoked integration API key'], 401);
        }

        $request->attributes->set('integration_api_key', $apiKey);
        $request->attributes->set('tenant_id', $apiKey->tenant_id);

        return $next($request);
    }
}
