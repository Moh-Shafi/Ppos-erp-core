<?php

return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'manual'),

    'gateways' => [
        'manual' => [
            'driver' => 'manual',
        ],
        'xendit' => [
            'driver' => 'xendit',
            'api_key' => env('XENDIT_API_KEY', ''),
            'webhook_token' => env('XENDIT_WEBHOOK_TOKEN', ''),
            'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
            'api_version' => env('XENDIT_API_VERSION', '2024-11-11'),
        ],
    ],

    'settlement' => [
        'sync_enabled' => env('XENDIT_SETTLEMENT_SYNC', false),
        'sync_schedule' => env('XENDIT_SETTLEMENT_SCHEDULE', 'daily'),
    ],
];
