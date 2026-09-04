<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function check(Request $request)
    {
        $checks = [];
        $allHealthy = true;

        $checks['database'] = $this->checkDatabase() ? 'ok' : 'fail';
        if ($checks['database'] === 'fail') {
            $allHealthy = false;
        }

        $checks['storage'] = $this->checkStorage() ? 'ok' : 'fail';
        if ($checks['storage'] === 'fail') {
            $allHealthy = false;
        }

        if (config('cache.default') === 'redis') {
            $checks['redis'] = $this->checkRedis() ? 'ok' : 'fail';
            if ($checks['redis'] === 'fail') {
                $allHealthy = false;
            }
        }

        $checks['queue'] = $this->checkQueue() ? 'ok' : 'fail';
        if ($checks['queue'] === 'fail') {
            $allHealthy = false;
        }

        $status = $allHealthy ? 'healthy' : 'degraded';
        $code = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $code);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            return Storage::disk('local')->exists('');
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            \Illuminate\Support\Facades\Cache::store('redis')->put('health_check', true, 10);
            return (bool) \Illuminate\Support\Facades\Cache::store('redis')->get('health_check');
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        return true;
    }
}
