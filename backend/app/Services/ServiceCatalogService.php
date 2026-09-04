<?php

namespace App\Services;

use App\Models\ServiceCatalog;
use App\Models\Product;

class ServiceCatalogService
{
    public function create(array $data): ServiceCatalog
    {
        $product = Product::create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'selling_price' => $data['selling_price'],
            'is_active' => true,
            'is_service' => true,
            'is_trackable' => false,
            'description' => $data['description'] ?? null,
        ]);

        return ServiceCatalog::create([
            'product_id' => $product->id,
            'duration_minutes' => $data['duration_minutes'],
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurring_interval' => $data['recurring_interval'] ?? null,
            'buffer_time_minutes' => $data['buffer_time_minutes'] ?? 0,
        ]);
    }

    public function update(int $serviceId, array $data): ServiceCatalog
    {
        $service = ServiceCatalog::with('product')->findOrFail($serviceId);

        if (isset($data['name'])) {
            $service->product->name = $data['name'];
        }
        if (isset($data['selling_price'])) {
            $service->product->selling_price = $data['selling_price'];
        }
        $service->product->save();

        if (isset($data['duration_minutes'])) {
            $service->duration_minutes = $data['duration_minutes'];
        }
        if (isset($data['buffer_time_minutes'])) {
            $service->buffer_time_minutes = $data['buffer_time_minutes'];
        }
        $service->save();

        return $service->fresh(['product']);
    }

    public function delete(int $serviceId): void
    {
        $service = ServiceCatalog::findOrFail($serviceId);
        $service->product->update(['is_active' => false]);
        $service->delete();
    }
}
