<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (!$user->two_factor_enabled) {
            return $next($request);
        }

        if (!$user->two_factor_verified_at || $user->two_factor_verified_at->lt(now()->subMinutes(30))) {
            return response()->json([
                'message' => 'Two-factor authentication verification required.',
                '2fa_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
