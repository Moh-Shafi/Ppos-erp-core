<?php

namespace App\Services;

use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\Store;
use App\Models\TableArea;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TableService
{
    public function createArea(int $storeId, string $name, int $sortOrder = 0): TableArea
    {
        return TableArea::create([
            'store_id' => $storeId,
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
    }

    public function createTable(int $storeId, int $areaId, array $data): RestaurantTable
    {
        return RestaurantTable::create([
            'store_id' => $storeId,
            'table_area_id' => $areaId,
            'name' => $data['name'],
            'code' => $data['code'],
            'capacity' => $data['capacity'] ?? 4,
            'status' => 'available',
        ]);
    }

    public function updateTableStatus(int $tableId, string $status): RestaurantTable
    {
        $table = RestaurantTable::findOrFail($tableId);
        $table->status = $status;
        $table->save();

        return $table;
    }

    public function generateQrCode(int $tableId): RestaurantTable
    {
        $table = RestaurantTable::findOrFail($tableId);
        $table->qr_code = Str::random(32);
        $table->save();

        return $table;
    }

    public function linkSaleToTable(Sale $sale, int $tableId): void
    {
        $table = RestaurantTable::withoutTenantScope()
            ->where('tenant_id', $sale->tenant_id)
            ->where('id', $tableId)
            ->first();

        if (!$table) {
            throw new \DomainException('Table not found');
        }

        $sale->table_id = $tableId;
        $sale->save();

        $table->status = 'occupied';
        $table->save();
    }
}
