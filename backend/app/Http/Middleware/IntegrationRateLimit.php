<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class IntegrationRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->attributes->get('integration_api_key');

        if (!$apiKey) {
            return $next($request);
        }

        $key = 'integration_api:' . $apiKey->id;

        if (RateLimiter::tooManyAttempts($key, 100)) {
            $retryAfter = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Rate limit exceeded. Try again in ' . $retryAfter . ' seconds.',
            ], 429, ['Retry-After' => $retryAfter]);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
