<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService = new ReservationService(),
    ) {}

    public function index(Request $request)
    {
        $query = Reservation::query()->with(['customer', 'table']);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }
        if ($request->filled('date')) {
            $query->where('reservation_date', $request->get('date'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->get('customer_id'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('reservation_date', 'desc')->orderBy('start_time')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
            'table_id' => 'nullable|integer',
            'reservation_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'party_size' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $reservation = $this->reservationService->create($validated);
        return response()->json(['data' => $reservation->load(['customer', 'table'])], 201);
    }

    public function show(int $id)
    {
        $reservation = Reservation::with(['customer', 'table', 'store'])->findOrFail($id);
        return response()->json(['data' => $reservation]);
    }

    public function update(Request $request, int $id)
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'table_id' => 'sometimes|nullable|integer',
            'party_size' => 'sometimes|integer|min:1',
            'notes' => 'sometimes|nullable|string',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
        ]);

        $reservation->update($validated);
        return response()->json(['data' => $reservation->fresh(['customer', 'table'])]);
    }

    public function confirm(int $id)
    {
        return response()->json(['data' => $this->reservationService->confirm($id)]);
    }

    public function seat(Request $request, int $id)
    {
        $validated = $request->validate(['table_id' => 'required|integer']);
        return response()->json(['data' => $this->reservationService->seat($id, $validated['table_id'])]);
    }

    public function complete(int $id)
    {
        return response()->json(['data' => $this->reservationService->complete($id)]);
    }

    public function cancel(Request $request, int $id)
    {
        $reason = $request->get('reason');
        return response()->json(['data' => $this->reservationService->cancel($id, $reason)]);
    }

    public function availability(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'party_size' => 'required|integer|min:1',
        ]);

        $tables = $this->reservationService->checkAvailability(
            $validated['store_id'],
            $validated['date'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['party_size'],
        );

        return response()->json(['data' => $tables]);
    }
}
