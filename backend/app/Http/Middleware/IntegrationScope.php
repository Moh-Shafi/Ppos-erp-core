<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntegrationScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $apiKey = $request->attributes->get('integration_api_key');

        if (!$apiKey) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$apiKey->hasScope($scope)) {
            return response()->json(['message' => 'Insufficient scope. Required: ' . $scope], 403);
        }

        return $next($request);
    }
}
