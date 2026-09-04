<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService,
        protected AuditService $auditService,
    ) {}

    public function enable(Request $request)
    {
        $user = $request->user();

        $result = $this->twoFactorService->enable($user);

        return response()->json($result);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($this->twoFactorService->verify($user, $request->code)) {
            $this->auditService->log('2fa.enabled', 'User', $user->id, null, null, $user->id, $user->tenant_id);

            return response()->json(['verified' => true]);
        }

        throw ValidationException::withMessages([
            'code' => ['The provided code is invalid.'],
        ]);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($this->twoFactorService->disable($user, $request->code)) {
            $this->auditService->log('2fa.disabled', 'User', $user->id, null, null, $user->id, $user->tenant_id);

            return response()->json(['disabled' => true]);
        }

        throw ValidationException::withMessages([
            'code' => ['The provided code is invalid.'],
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->two_factor_enabled,
            'enabled_at' => $user->twoFactorAuth?->enabled_at?->toIso8601String(),
            'backup_codes_remaining' => $this->twoFactorService->getBackupCodesRemaining($user),
            'last_used_at' => $user->twoFactorAuth?->last_used_at?->toIso8601String(),
        ]);
    }

    public function regenerateBackupCodes(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        $codes = $this->twoFactorService->regenerateBackupCodes($user, $request->code);

        if ($codes === null) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        return response()->json(['backup_codes' => $codes]);
    }

    public function loginWith2fa(Request $request)
    {
        $request->validate([
            '2fa_token' => 'required|string',
            'code' => 'required|string',
            'is_backup' => 'boolean',
        ]);

        $tokenData = Cache::pull('2fa_token:' . $request->input('2fa_token'));

        if (!$tokenData) {
            throw ValidationException::withMessages([
                '2fa_token' => ['Invalid or expired 2FA token.'],
            ]);
        }

        $user = \App\Models\User::find($tokenData['user_id']);

        if (!$user) {
            throw ValidationException::withMessages([
                '2fa_token' => ['User not found.'],
            ]);
        }

        $verified = false;

        if ($request->boolean('is_backup')) {
            $verified = $this->twoFactorService->verifyBackupCode($user, $request->code);
        } else {
            $verified = $this->twoFactorService->verify($user, $request->code);
        }

        if (!$verified) {
            throw ValidationException::withMessages([
                'code' => ['Invalid 2FA code.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->auditService->log('login', 'User', $user->id, null, null, $user->id, $user->tenant_id);

        $config = app(\App\Services\RegistrationService::class)->getConfig($user->tenant_id, $user->id);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user->load(['tenant', 'role']),
            'modules' => $config['modules'],
            'features' => $config['features'],
            'permissions' => $config['permissions'],
            'stores' => $config['stores'],
            'business_profile' => $config['business_profile'],
        ]);
    }

    public static function generateTempToken(int $userId): string
    {
        $token = Str::random(40);
        $ttl = config('security.two_factor.temp_token_ttl', 300);

        Cache::put('2fa_token:' . $token, [
            'user_id' => $userId,
            'attempts' => 0,
        ], $ttl);

        return $token;
    }
}
