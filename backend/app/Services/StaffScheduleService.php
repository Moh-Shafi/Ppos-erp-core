<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\StaffSchedule;
use Illuminate\Support\Carbon;

class StaffScheduleService
{
    public function create(array $data): StaffSchedule
    {
        return StaffSchedule::create([
            'user_id' => $data['user_id'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'is_available' => $data['is_available'] ?? true,
            'effective_from' => $data['effective_from'],
            'effective_until' => $data['effective_until'] ?? null,
        ]);
    }

    public function getAvailability(int $userId, string $date): array
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek;

        $schedules = StaffSchedule::withoutTenantScope()
            ->where('user_id', $userId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->where('effective_from', '<=', $carbonDate->toDateString())
            ->where(function ($q) use ($carbonDate) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $carbonDate->toDateString());
            })
            ->orderBy('start_time')
            ->get();

        $appointments = Appointment::withoutTenantScope()
            ->where('user_id', $userId)
            ->where('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->orderBy('start_time')
            ->get();

        $slots = [];
        foreach ($schedules as $schedule) {
            $slots = array_merge($slots, $this->calculateFreeSlots($schedule, $appointments));
        }

        return $slots;
    }

    public function getAvailableStaff(int $tenantId, string $date, string $startTime, string $endTime): array
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek;

        $schedules = StaffSchedule::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->where('effective_from', '<=', $carbonDate->toDateString())
            ->where(function ($q) use ($carbonDate) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $carbonDate->toDateString());
            })
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->with('user')
            ->get();

        $availableStaff = [];
        foreach ($schedules as $schedule) {
            $hasConflict = Appointment::withoutTenantScope()
                ->where('user_id', $schedule->user_id)
                ->where('appointment_date', $date)
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->exists();

            if (!$hasConflict) {
                $availableStaff[] = [
                    'user_id' => $schedule->user_id,
                    'user_name' => $schedule->user->name,
                    'slots' => $this->getAvailability($schedule->user_id, $date),
                ];
            }
        }

        return $availableStaff;
    }

    protected function calculateFreeSlots(StaffSchedule $schedule, $appointments): array
    {
        $slots = [];
        $scheduleStart = $schedule->start_time;
        $scheduleEnd = $schedule->end_time;

        $busyPeriods = $appointments->map(function ($apt) {
            return ['start' => $apt->start_time, 'end' => $apt->end_time];
        })->sortBy('start')->values();

        $currentTime = $scheduleStart;

        foreach ($busyPeriods as $busy) {
            if ($currentTime < $busy['start']) {
                $slots[] = ['start_time' => $currentTime, 'end_time' => $busy['start']];
            }
            $currentTime = max($currentTime, $busy['end']);
        }

        if ($currentTime < $scheduleEnd) {
            $slots[] = ['start_time' => $currentTime, 'end_time' => $scheduleEnd];
        }

        return $slots;
    }
}
