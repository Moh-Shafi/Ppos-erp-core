<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use App\Services\SecurityService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;

class AdminSecurityController extends Controller
{
    public function __construct(
        protected SecurityService $securityService,
        protected TwoFactorService $twoFactorService,
        protected AuditService $auditService,
    ) {}

    public function unlockUser(int $id)
    {
        $user = User::findOrFail($id);

        $this->securityService->unlockUser($id);

        $this->auditService->log(
            'account.unlocked',
            'User',
            $id,
            null,
            null,
            request()->user()->id,
            request()->user()->tenant_id,
        );

        return response()->json(['unlocked' => true, 'user_id' => $id]);
    }

    public function reset2fa(int $id)
    {
        $user = User::findOrFail($id);

        $this->twoFactorService->resetForUser($user);

        $this->auditService->log(
            '2fa.reset',
            'User',
            $id,
            null,
            null,
            request()->user()->id,
            request()->user()->tenant_id,
        );

        return response()->json(['reset' => true, 'user_id' => $id]);
    }
}
