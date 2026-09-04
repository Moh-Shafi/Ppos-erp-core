<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentService as AppointmentServiceModel;
use App\Models\ServiceCatalog;
use App\Models\StaffSchedule;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function create(array $data): Appointment
    {
        $services = $data['services'];
        $serviceCatalogIds = collect($services)->pluck('service_catalog_id')->toArray();

        $catalogItems = ServiceCatalog::withoutTenantScope()
            ->whereIn('id', $serviceCatalogIds)
            ->with('product')
            ->get()
            ->keyBy('id');

        $totalDuration = 0;
        foreach ($services as $svc) {
            $catalog = $catalogItems->get($svc['service_catalog_id']);
            if (!$catalog) {
                throw new \DomainException("Service catalog item not found");
            }
            $qty = $svc['quantity'] ?? 1;
            $totalDuration += $catalog->duration_minutes * $qty;
            $totalDuration += $catalog->buffer_time_minutes;
        }

        $startTime = $data['start_time'];
        $endTime = date('H:i:s', strtotime($startTime) + $totalDuration * 60);

        if (!empty($data['user_id'])) {
            $this->checkStaffAvailability($data['user_id'], $data['appointment_date'], $startTime, $endTime, $data['store_id']);
        }

        $isRecurring = $data['is_recurring'] ?? false;
        $recurringInterval = $data['recurring_interval'] ?? null;
        $recurringEndDate = $data['recurring_end_date'] ?? null;

        if ($isRecurring && $recurringInterval && $recurringEndDate) {
            return $this->createRecurringSeries($data, $services, $catalogItems, $startTime, $endTime, $recurringInterval, $recurringEndDate);
        }

        return $this->createSingleAppointment($data, $services, $catalogItems, $startTime, $endTime);
    }

    protected function createSingleAppointment(array $data, array $services, $catalogItems, string $startTime, string $endTime): Appointment
    {
        return DB::transaction(function () use ($data, $services, $catalogItems, $startTime, $endTime) {
            $appointment = Appointment::create([
                'store_id' => $data['store_id'],
                'customer_id' => $data['customer_id'],
                'user_id' => $data['user_id'] ?? null,
                'appointment_date' => $data['appointment_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($services as $svc) {
                $catalog = $catalogItems->get($svc['service_catalog_id']);
                $qty = $svc['quantity'] ?? 1;

                AppointmentServiceModel::create([
                    'appointment_id' => $appointment->id,
                    'service_catalog_id' => $svc['service_catalog_id'],
                    'duration_minutes' => $catalog->duration_minutes * $qty,
                    'price' => (float) $catalog->product->selling_price * $qty,
                ]);
            }

            return $appointment->fresh(['services.serviceCatalog.product', 'customer', 'staff']);
        });
    }

    protected function createRecurringSeries(array $data, array $services, $catalogItems, string $startTime, string $endTime, string $interval, string $endDate): Appointment
    {
        $appointments = [];
        $currentDate = \Carbon\Carbon::parse($data['appointment_date']);
        $end = \Carbon\Carbon::parse($endDate);

        while ($currentDate <= $end) {
            $data['appointment_date'] = $currentDate->format('Y-m-d');
            $appointments[] = $this->createSingleAppointment($data, $services, $catalogItems, $startTime, $endTime);

            $currentDate = match ($interval) {
                'daily' => $currentDate->addDay(),
                'weekly' => $currentDate->addWeek(),
                'monthly' => $currentDate->addMonth(),
                default => $currentDate->addDay(),
            };
        }

        return $appointments[0];
    }

    public function confirm(int $appointmentId): Appointment
    {
        $appointment = Appointment::findOrFail($appointmentId);
        if ($appointment->status !== 'pending') {
            throw new \DomainException('Only pending appointments can be confirmed');
        }
        $appointment->status = 'confirmed';
        $appointment->save();
        return $appointment;
    }

    public function start(int $appointmentId): Appointment
    {
        $appointment = Appointment::findOrFail($appointmentId);
        if ($appointment->status !== 'confirmed') {
            throw new \DomainException('Only confirmed appointments can be started');
        }
        $appointment->status = 'in_progress';
        $appointment->save();
        return $appointment;
    }

    public function complete(int $appointmentId, array $payments): Appointment
    {
        $appointment = Appointment::findOrFail($appointmentId);
        if ($appointment->status !== 'in_progress') {
            throw new \DomainException('Only in-progress appointments can be completed');
        }

        $saleService = app(SaleService::class);

        $items = [];
        foreach ($appointment->services as $svc) {
            $items[] = [
                'product_id' => $svc->serviceCatalog->product_id,
                'quantity' => 1,
                'unit_price' => (float) $svc->price,
            ];
        }

        $sale = $saleService->checkout([
            'store_id' => $appointment->store_id,
            'customer_id' => $appointment->customer_id,
            'items' => $items,
            'payments' => $payments,
            'appointment_id' => $appointment->id,
        ]);

        $appointment->sale_id = $sale->id;
        $appointment->status = 'completed';
        $appointment->save();

        return $appointment->fresh(['services.serviceCatalog.product', 'customer', 'staff', 'sale']);
    }

    public function cancel(int $appointmentId, ?string $reason = null): Appointment
    {
        $appointment = Appointment::findOrFail($appointmentId);
        if (in_array($appointment->status, ['completed', 'cancelled', 'no_show'])) {
            throw new \DomainException('Cannot cancel a completed or already cancelled appointment');
        }
        $appointment->status = 'cancelled';
        if ($reason) {
            $appointment->notes = ($appointment->notes ?? '') . "\n[Cancelled: {$reason}]";
        }
        $appointment->save();
        return $appointment;
    }

    public function getCalendar(int $storeId, string $view, string $date): array
    {
        $carbonDate = \Carbon\Carbon::parse($date);

        [$startDate, $endDate] = match ($view) {
            'day' => [$carbonDate->copy()->startOfDay(), $carbonDate->copy()->endOfDay()],
            'week' => [$carbonDate->copy()->startOfWeek(), $carbonDate->copy()->endOfWeek()],
            'month' => [$carbonDate->copy()->startOfMonth(), $carbonDate->copy()->endOfMonth()],
            default => [$carbonDate->copy()->startOfDay(), $carbonDate->copy()->endOfDay()],
        };

        $appointments = Appointment::withoutTenantScope()
            ->where('store_id', $storeId)
            ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['customer', 'staff', 'services.serviceCatalog.product'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        return [
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'appointments' => $appointments,
        ];
    }

    public function linkSaleToAppointment(Sale $sale, int $appointmentId): void
    {
        $appointment = Appointment::withoutTenantScope()
            ->where('tenant_id', $sale->tenant_id)
            ->where('id', $appointmentId)
            ->first();

        if (!$appointment) {
            throw new \DomainException('Appointment not found');
        }

        $appointment->sale_id = $sale->id;
        $appointment->status = 'completed';
        $appointment->save();
    }

    protected function checkStaffAvailability(int $userId, string $date, string $startTime, string $endTime, int $storeId): void
    {
        $carbonDate = \Carbon\Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek;

        $schedule = StaffSchedule::withoutTenantScope()
            ->where('user_id', $userId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->where('effective_from', '<=', $carbonDate->toDateString())
            ->where(function ($q) use ($carbonDate) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $carbonDate->toDateString());
            })
            ->first();

        if (!$schedule) {
            throw new \DomainException('Staff member is not scheduled for this day');
        }

        if ($startTime < $schedule->start_time || $endTime > $schedule->end_time) {
            throw new \DomainException('Requested time is outside staff schedule hours');
        }

        $overlapping = Appointment::withoutTenantScope()
            ->where('user_id', $userId)
            ->where('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($overlapping) {
            throw new \DomainException('Staff member has conflicting appointment at this time');
        }
    }
}
