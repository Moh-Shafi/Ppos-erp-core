<?php

return [
    'observed_models' => [
        \App\Models\Product::class,
        \App\Models\Category::class,
        \App\Models\Sale::class,
        \App\Models\Payment::class,
        \App\Models\Purchase::class,
        \App\Models\Customer::class,
        \App\Models\Supplier::class,
        \App\Models\TenantIntegration::class,
        \App\Models\WebhookEndpoint::class,
        \App\Models\IntegrationApiKey::class,
    ],

    'retention_days' => env('AUDIT_RETENTION_DAYS', 90),
    'async' => env('AUDIT_ASYNC', false),
    'queue' => 'audit',

    'redacted_fields' => [
        'password', 'password_hash', 'secret', 'api_key', 'token',
        'whsec', 'client_secret', 'private_key', 'access_token', 'refresh_token',
    ],
];
