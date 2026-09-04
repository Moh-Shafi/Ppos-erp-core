<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function create(array $data): Reservation
    {
        return Reservation::create([
            'store_id' => $data['store_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'table_id' => $data['table_id'] ?? null,
            'reservation_date' => $data['reservation_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'party_size' => $data['party_size'],
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function checkAvailability(int $storeId, string $date, string $startTime, string $endTime, int $partySize): array
    {
        $tables = RestaurantTable::withoutTenantScope()
            ->where('store_id', $storeId)
            ->where('capacity', '>=', $partySize)
            ->where('status', '!=', 'cleaning')
            ->get();

        $reservedTableIds = Reservation::withoutTenantScope()
            ->where('store_id', $storeId)
            ->where('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'seated'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->pluck('table_id')
            ->toArray();

        return $tables->map(function ($table) use ($reservedTableIds) {
            return [
                'table_id' => $table->id,
                'table_name' => $table->name,
                'available' => !in_array($table->id, $reservedTableIds),
            ];
        })->toArray();
    }

    public function confirm(int $reservationId): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);
        if ($reservation->status !== 'pending') {
            throw new \DomainException('Only pending reservations can be confirmed');
        }
        $reservation->status = 'confirmed';
        $reservation->save();
        return $reservation;
    }

    public function seat(int $reservationId, int $tableId): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);
        if (!in_array($reservation->status, ['confirmed', 'pending'])) {
            throw new \DomainException('Only confirmed or pending reservations can be seated');
        }

        $table = RestaurantTable::withoutTenantScope()
            ->where('tenant_id', $reservation->tenant_id)
            ->where('id', $tableId)
            ->first();

        if (!$table) {
            throw new \DomainException('Table not found');
        }

        if ($table->status === 'occupied') {
            throw new \DomainException('Table is already occupied');
        }

        DB::transaction(function () use ($reservation, $tableId, $table) {
            $reservation->table_id = $tableId;
            $reservation->status = 'seated';
            $reservation->save();

            $table->status = 'occupied';
            $table->save();
        });

        return $reservation;
    }

    public function complete(int $reservationId): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);
        if ($reservation->status !== 'seated') {
            throw new \DomainException('Only seated reservations can be completed');
        }

        DB::transaction(function () use ($reservation) {
            $reservation->status = 'completed';
            $reservation->save();

            if ($reservation->table_id) {
                $table = RestaurantTable::withoutTenantScope()
                    ->where('tenant_id', $reservation->tenant_id)
                    ->where('id', $reservation->table_id)
                    ->first();
                if ($table) {
                    $table->status = 'cleaning';
                    $table->save();
                }
            }
        });

        return $reservation;
    }

    public function cancel(int $reservationId, ?string $reason = null): Reservation
    {
        $reservation = Reservation::findOrFail($reservationId);
        if (in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
            throw new \DomainException('Cannot cancel a completed or already cancelled reservation');
        }

        $reservation->status = 'cancelled';
        if ($reason) {
            $reservation->notes = ($reservation->notes ?? '') . "\n[Cancelled: {$reason}]";
        }
        $reservation->save();

        return $reservation;
    }
}
