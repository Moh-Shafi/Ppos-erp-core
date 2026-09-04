<?php

return [
    'password' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 12),
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'history_count' => 5,
    ],

    'lockout' => [
        'thresholds' => [5, 10, 15],
        'durations' => [900, 3600, 86400],
    ],

    'rate_limit' => [
        'tenant' => env('RATE_LIMIT_TENANT_PER_MIN', 1000),
        'user' => env('RATE_LIMIT_USER_PER_MIN', 200),
        'write' => env('RATE_LIMIT_WRITE_PER_MIN', 60),
        'read' => env('RATE_LIMIT_READ_PER_MIN', 300),
    ],

    'xss' => [
        'skip_fields' => ['description', 'notes', 'receipt_footer', 'message'],
    ],

    'two_factor' => [
        'issuer' => env('TWO_FA_ISSUER', 'POS-SaaS'),
        'window' => env('TWO_FA_WINDOW', 1),
        'backup_codes_count' => 10,
        'temp_token_ttl' => 300,
        'max_2fa_attempts' => 5,
    ],
];
