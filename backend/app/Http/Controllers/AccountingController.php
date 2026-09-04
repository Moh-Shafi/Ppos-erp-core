<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Services\AccountBalanceService;
use App\Services\JournalEntryService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountingController extends Controller
{
    public function __construct(
        private JournalEntryService $journalEntryService,
        private ReportService $reportService,
        private AccountBalanceService $accountBalanceService,
    ) {}

    public function accounts(Request $request)
    {
        $query = Account::query();

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->has('flat') && $request->get('flat')) {
            return response()->json($query->orderBy('code')->get());
        }

        $roots = $query->whereNull('parent_id')->with('children')->orderBy('code')->get();
        return response()->json($roots);
    }

    public function storeAccount(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|integer|exists:accounts,id',
            'is_bank' => 'nullable|boolean',
        ]);

        $tenant = $request->user()->tenant;

        $existing = Account::where('tenant_id', $tenant->id)
            ->where('code', $validated['code'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Account code already exists'], 422);
        }

        $account = new Account($validated);
        $account->tenant_id = $tenant->id;
        $account->is_system = false;
        $account->is_active = true;
        $account->save();

        return response()->json(['data' => $account], 201);
    }

    public function updateAccount(Request $request, int $id)
    {
        $account = Account::findOrFail($id);

        if ($account->is_system) {
            return response()->json(['message' => 'Cannot update system account'], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $account->update($validated);

        return response()->json(['data' => $account]);
    }

    public function destroyAccount(int $id)
    {
        $account = Account::findOrFail($id);

        if ($account->is_system) {
            return response()->json(['message' => 'Cannot delete system account'], 422);
        }

        if ($account->journalLines()->exists()) {
            return response()->json(['message' => 'Account has journal entries'], 422);
        }

        $account->delete();

        return response()->json(['message' => 'OK']);
    }

    public function journalEntries(Request $request)
    {
        $query = JournalEntry::with('lines.account');

        if ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->get('to'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->get('source'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $entries = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($entries);
    }

    public function storeJournalEntry(Request $request)
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'description' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
        ]);

        $tenant = $request->user()->tenant;

        $data = [
            'tenant_id' => $tenant->id,
            'entry_date' => $validated['entry_date'],
            'description' => $validated['description'] ?? null,
            'source' => 'manual',
            'posted_by' => Auth::id(),
            'lines' => $validated['lines'],
        ];

        $entry = $this->journalEntryService->post($data);

        return response()->json(['data' => $entry], 201);
    }

    public function showJournalEntry(int $id)
    {
        $entry = JournalEntry::with('lines.account')->findOrFail($id);
        return response()->json(['data' => $entry]);
    }

    public function fiscalPeriods(Request $request)
    {
        $query = FiscalPeriod::query();

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        return response()->json($query->orderBy('start_date', 'desc')->get());
    }

    public function storeFiscalPeriod(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $tenant = $request->user()->tenant;

        $period = new FiscalPeriod($validated);
        $period->tenant_id = $tenant->id;
        $period->status = 'open';
        $period->save();

        return response()->json(['data' => $period], 201);
    }

    public function closeFiscalPeriod(Request $request, int $id)
    {
        $period = FiscalPeriod::findOrFail($id);

        if ($period->status === 'closed') {
            return response()->json(['message' => 'Period is already closed'], 422);
        }

        $period->status = 'closed';
        $period->save();

        $this->accountBalanceService->recalculate($period->tenant_id, $period->id);

        return response()->json(['data' => $period]);
    }

    public function trialBalance(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $tenant = $request->user()->tenant;
        $report = $this->reportService->trialBalance($tenant->id, $validated['start_date'], $validated['end_date']);

        return response()->json($report);
    }

    public function profitAndLoss(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $tenant = $request->user()->tenant;
        $report = $this->reportService->profitAndLoss($tenant->id, $validated['start_date'], $validated['end_date']);

        return response()->json($report);
    }

    public function balanceSheet(Request $request)
    {
        $validated = $request->validate([
            'as_of' => 'required|date',
        ]);

        $tenant = $request->user()->tenant;
        $report = $this->reportService->balanceSheet($tenant->id, $validated['as_of']);

        return response()->json($report);
    }

    public function ledger(Request $request, int $id)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $report = $this->reportService->ledger($id, $validated['start_date'], $validated['end_date']);

        return response()->json($report);
    }
}
