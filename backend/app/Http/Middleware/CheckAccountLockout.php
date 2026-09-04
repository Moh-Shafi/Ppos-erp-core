<?php

namespace App\Http\Middleware;

use App\Models\AccountLockout;
use Closure;
use Illuminate\Http\Request;

class CheckAccountLockout
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('testing', 'local')) {
            return $next($request);
        }

        $username = $request->input('email');

        if (!$username) {
            return $next($request);
        }

        $lockout = AccountLockout::where('username', $username)->first();

        if ($lockout && $lockout->isLocked()) {
            $retryAfter = $lockout->retryAfter();

            return response()->json([
                'message' => 'Account is locked due to too many failed attempts.',
                'locked_until' => $lockout->locked_until->toIso8601String(),
                'retry_after' => $retryAfter,
            ], 423, ['Retry-After' => $retryAfter]);
        }

        return $next($request);
    }
}
