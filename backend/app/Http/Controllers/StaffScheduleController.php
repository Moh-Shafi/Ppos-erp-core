<?php

namespace App\Http\Controllers;

use App\Models\StaffSchedule;
use App\Services\StaffScheduleService;
use Illuminate\Http\Request;

class StaffScheduleController extends Controller
{
    public function __construct(
        private readonly StaffScheduleService $staffScheduleService = new StaffScheduleService(),
    ) {}

    public function index(Request $request)
    {
        $query = StaffSchedule::query()->with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->get('user_id'));
        }
        if ($request->filled('day_of_week')) {
            $query->where('day_of_week', (int) $request->get('day_of_week'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('day_of_week')->orderBy('start_time')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_available' => 'boolean',
            'effective_from' => 'required|date',
            'effective_until' => 'nullable|date|after:effective_from',
        ]);

        $schedule = $this->staffScheduleService->create($validated);
        return response()->json(['data' => $schedule->load('user')], 201);
    }

    public function update(Request $request, int $id)
    {
        $schedule = StaffSchedule::findOrFail($id);

        $validated = $request->validate([
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'is_available' => 'sometimes|boolean',
            'effective_until' => 'sometimes|nullable|date',
        ]);

        $schedule->update($validated);
        return response()->json(['data' => $schedule->fresh(['user'])]);
    }

    public function destroy(int $id)
    {
        StaffSchedule::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function availability(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'service_id' => 'nullable|integer',
            'store_id' => 'nullable|integer',
        ]);

        $tenantId = $request->user()->tenant_id;
        $staff = $this->staffScheduleService->getAvailableStaff(
            $tenantId,
            $validated['date'],
            '00:00',
            '23:59',
        );

        return response()->json(['data' => $staff]);
    }
}
