<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        RateLimiter::for('auth', function (Request $request) {
            if ($this->app->environment('local', 'testing')) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }
            return Limit::perMinute(5);
        });

        RateLimiter::for('api', function (Request $request) {
            if ($this->app->environment('local', 'testing')) {
                return Limit::none();
            }
            return Limit::perMinute(60);
        });

        RateLimiter::for('tenant', function (Request $request) {
            if (app()->environment('testing', 'local')) {
                return Limit::none();
            }
            $tenantId = $request->user()?->tenant_id ?? 'anonymous';
            return Limit::perMinute(config('security.rate_limit.tenant', 1000))
                ->by($tenantId)
                ->response(fn () => response()->json([
                    'message' => 'Tenant rate limit exceeded.',
                ], 429));
        });

        RateLimiter::for('user', function (Request $request) {
            if (app()->environment('testing', 'local')) {
                return Limit::none();
            }
            $userId = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(config('security.rate_limit.user', 200))
                ->by($userId)
                ->response(fn () => response()->json([
                    'message' => 'User rate limit exceeded.',
                ], 429));
        });

        RateLimiter::for('write', function (Request $request) {
            if (app()->environment('testing', 'local')) {
                return Limit::none();
            }
            $userId = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(config('security.rate_limit.write', 60))
                ->by('write:' . $userId);
        });

        RateLimiter::for('read', function (Request $request) {
            if (app()->environment('testing', 'local')) {
                return Limit::none();
            }
            $userId = $request->user()?->id ?? $request->ip();
            return Limit::perMinute(config('security.rate_limit.read', 300))
                ->by('read:' . $userId);
        });

        RateLimiter::for('health', function (Request $request) {
            if (app()->environment('testing', 'local')) {
                return Limit::none();
            }
            return Limit::perMinute(60)->by($request->ip());
        });

        $this->registerAuditObservers();
    }

    private function registerAuditObservers(): void
    {
        $models = config('audit.observed_models', []);
        foreach ($models as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
