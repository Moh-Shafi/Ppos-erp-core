<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Payment;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function export(Request $request)
    {
        $user = $request->user();

        $data = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'sales' => Sale::where('cashier_id', $user->id)
                ->orWhere('tenant_id', $user->tenant_id)
                ->with('items:id,sale_id,product_name,quantity,unit_price,subtotal')
                ->limit(1000)
                ->get()
                ->toArray(),
            'payments' => Payment::whereHas('sale', function ($q) use ($user) {
                $q->where('tenant_id', $user->tenant_id);
            })->limit(1000)->get()->toArray(),
            'audit_logs' => AuditLog::where('user_id', $user->id)
                ->limit(500)
                ->get(['id', 'action', 'entity_type', 'entity_id', 'created_at'])
                ->toArray(),
        ];

        $this->auditService->log('account.exported', 'User', $user->id, null, null, $user->id, $user->tenant_id);

        $filename = "account-export-{$user->id}-" . now()->format('Y-m-d') . '.json';

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function delete(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password verification failed.'],
            ]);
        }

        $this->auditService->log('account.deletion_requested', 'User', $user->id, null, null, $user->id, $user->tenant_id);

        $user->name = 'Deleted User';
        $user->email = "deleted_{$user->id}@local";
        $user->save();

        $user->delete();

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Account scheduled for deletion in 30 days.',
            'scheduled_purge_at' => now()->addDays(30)->toIso8601String(),
        ], 202);
    }

    public function consent(Request $request)
    {
        $user = $request->user();

        $consentLog = AuditLog::where('user_id', $user->id)
            ->where('action', 'registration')
            ->orWhere(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('action', 'like', '%consent%');
            })
            ->first();

        return response()->json([
            'consented_at' => $user->created_at?->toIso8601String(),
            'consent_type' => 'registration',
            'privacy_policy_version' => '1.0',
            'data_types' => ['profile', 'sales', 'payments', 'audit_logs'],
        ]);
    }
}
