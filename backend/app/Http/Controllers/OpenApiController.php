<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function spec(): JsonResponse
    {
        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'POS-SaaS API',
                'version' => '1.0.0',
                'description' => 'POS Restoran SaaS API - Phase 10',
            ],
            'servers' => [
                ['url' => url('/api/v1'), 'description' => 'API Server'],
            ],
            'paths' => [
                '/auth/register' => [
                    'post' => [
                        'summary' => 'Register a new tenant',
                        'tags' => ['Auth'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                            'password' => ['type' => 'string', 'minLength' => 12],
                                            'store_name' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/auth/login' => [
                    'post' => [
                        'summary' => 'Login',
                        'tags' => ['Auth'],
                    ],
                ],
                '/auth/login-2fa' => [
                    'post' => [
                        'summary' => 'Complete login with 2FA code',
                        'tags' => ['Auth', '2FA'],
                    ],
                ],
                '/auth/2fa/enable' => [
                    'post' => [
                        'summary' => 'Enable 2FA',
                        'tags' => ['2FA'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
                '/auth/2fa/verify' => [
                    'post' => [
                        'summary' => 'Verify 2FA code',
                        'tags' => ['2FA'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
                '/auth/2fa/disable' => [
                    'post' => [
                        'summary' => 'Disable 2FA',
                        'tags' => ['2FA'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
                '/auth/2fa/status' => [
                    'get' => [
                        'summary' => 'Get 2FA status',
                        'tags' => ['2FA'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
                '/health' => [
                    'get' => [
                        'summary' => 'Health check',
                        'tags' => ['Health'],
                    ],
                ],
                '/account/export' => [
                    'get' => [
                        'summary' => 'Export user data (PDP compliance)',
                        'tags' => ['Account', 'PDP'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
                '/account' => [
                    'delete' => [
                        'summary' => 'Delete account (PDP compliance)',
                        'tags' => ['Account', 'PDP'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
                '/account/consent' => [
                    'get' => [
                        'summary' => 'Get consent info (PDP compliance)',
                        'tags' => ['Account', 'PDP'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
                '/audit-logs' => [
                    'get' => [
                        'summary' => 'List audit logs',
                        'tags' => ['Audit'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
                '/audit-logs/export' => [
                    'get' => [
                        'summary' => 'Export audit logs as CSV',
                        'tags' => ['Audit'],
                        'security' => [['bearerAuth' => []]],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
        ];

        return response()->json($spec);
    }
}
