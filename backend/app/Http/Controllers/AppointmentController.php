<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService = new AppointmentService(),
    ) {}

    public function index(Request $request)
    {
        $query = Appointment::query()->with(['customer', 'staff', 'services.serviceCatalog.product']);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->get('store_id'));
        }
        if ($request->filled('date')) {
            $query->where('appointment_date', $request->get('date'));
        }
        if ($request->filled('from_date')) {
            $query->where('appointment_date', '>=', $request->get('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('appointment_date', '<=', $request->get('to_date'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('staff_id')) {
            $query->where('user_id', (int) $request->get('staff_id'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->get('customer_id'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('appointment_date', 'desc')->orderBy('start_time')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'user_id' => 'nullable|integer',
            'appointment_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'services' => 'required|array|min:1',
            'services.*.service_catalog_id' => 'required|integer',
            'services.*.quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'is_recurring' => 'boolean',
            'recurring_interval' => 'nullable|in:daily,weekly,monthly',
            'recurring_end_date' => 'nullable|date|after:appointment_date',
        ]);

        try {
            $appointment = $this->appointmentService->create($validated);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $appointment], 201);
    }

    public function show(int $id)
    {
        $appointment = Appointment::with(['customer', 'staff', 'services.serviceCatalog.product', 'sale', 'store'])->findOrFail($id);
        return response()->json(['data' => $appointment]);
    }

    public function update(Request $request, int $id)
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'sometimes|nullable|integer',
            'notes' => 'sometimes|nullable|string',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
        ]);

        $appointment->update($validated);
        return response()->json(['data' => $appointment->fresh(['customer', 'staff', 'services.serviceCatalog.product'])]);
    }

    public function confirm(int $id)
    {
        return response()->json(['data' => $this->appointmentService->confirm($id)]);
    }

    public function start(int $id)
    {
        return response()->json(['data' => $this->appointmentService->start($id)]);
    }

    public function complete(Request $request, int $id)
    {
        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:cash,qris,card,bank_transfer',
            'payments.*.amount' => 'required|numeric|min:0',
        ]);

        return response()->json(['data' => $this->appointmentService->complete($id, $validated['payments'])]);
    }

    public function cancel(Request $request, int $id)
    {
        $reason = $request->get('reason');
        return response()->json(['data' => $this->appointmentService->cancel($id, $reason)]);
    }

    public function calendar(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'view' => 'required|in:day,week,month',
            'date' => 'required|date',
        ]);

        $data = $this->appointmentService->getCalendar($validated['store_id'], $validated['view'], $validated['date']);
        return response()->json(['data' => $data]);
    }
}
