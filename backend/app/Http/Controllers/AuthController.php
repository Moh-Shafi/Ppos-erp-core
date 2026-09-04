<?php

namespace App\Http\Controllers;

use App\Models\BusinessType;
use App\Services\AuditService;
use App\Services\RegistrationService;
use App\Services\SecurityService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected RegistrationService $registrationService,
        protected AuditService $auditService,
        protected SecurityService $securityService,
        protected TwoFactorService $twoFactorService,
    ) {}

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(config('security.password.min_length', 12))
                ->mixedCase()
                ->numbers()
                ->symbols()],
            'store_name' => 'required|string|max:255',
            'business_type_id' => 'nullable|integer|exists:business_types,id',
        ]);

        $user = $this->registrationService->register($validated);

        $token = $user->createToken('auth_token')->plainTextToken;

        $config = $this->registrationService->getConfig($user->tenant_id, $user->id);

        return response()->json([
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user->load(['tenant', 'role']),
            'modules' => $config['modules'],
            'features' => $config['features'],
            'permissions' => $config['permissions'],
            'stores' => $config['stores'],
            'business_profile' => $config['business_profile'],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            if (!app()->environment('testing', 'local')) {
                $this->securityService->recordFailedAttempt($request->email);
            }
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!app()->environment('testing', 'local')) {
            $this->securityService->resetFailedAttempts($request->email);
        }

        if ($user->two_factor_enabled) {
            $tempToken = \App\Http\Controllers\TwoFactorController::generateTempToken($user->id);

            return response()->json([
                '2fa_required' => true,
                '2fa_token' => $tempToken,
                'expires_in' => config('security.two_factor.temp_token_ttl', 300),
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->auditService->log('login', 'User', $user->id, null, null, $user->id, $user->tenant_id);

        $config = $this->registrationService->getConfig($user->tenant_id, $user->id);

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

    public function logout(Request $request)
    {
        $this->auditService->log('logout', 'User', $request->user()->id);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $config = $this->registrationService->getConfig($user->tenant_id, $user->id);

        return response()->json([
            'user' => $user->load(['tenant', 'role']),
            'modules' => $config['modules'],
            'features' => $config['features'],
            'permissions' => $config['permissions'],
            'stores' => $config['stores'],
            'business_profile' => $config['business_profile'],
        ]);
    }
}
