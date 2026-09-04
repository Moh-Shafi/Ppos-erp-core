<?php

use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckModule;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckAccountLockout;
use App\Http\Middleware\IntegrationKeyAuth;
use App\Http\Middleware\IntegrationScope;
use App\Http\Middleware\IntegrationRateLimit;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\XssSanitizer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'permission' => CheckPermission::class,
            'module' => CheckModule::class,
            'feature' => CheckFeature::class,
            'integration.key' => IntegrationKeyAuth::class,
            'integration.scope' => IntegrationScope::class,
            'integration.rate' => IntegrationRateLimit::class,
            'xss' => XssSanitizer::class,
            'lockout' => CheckAccountLockout::class,
            '2fa' => RequireTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
