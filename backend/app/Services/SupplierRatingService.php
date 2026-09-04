<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierRating;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierRatingService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function listRatings(Supplier $supplier, int $perPage = 20)
    {
        return SupplierRating::where('tenant_id', $supplier->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->with('ratedBy:id,name,email')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function createRating(Supplier $supplier, array $data): SupplierRating
    {
        $tenantId = $supplier->tenant_id;

        return DB::transaction(function () use ($supplier, $data, $tenantId) {
            $rating = SupplierRating::create([
                'tenant_id' => $tenantId,
                'supplier_id' => $supplier->id,
                'rating' => $data['rating'],
                'criteria' => $data['criteria'],
                'note' => $data['note'] ?? null,
                'rated_by' => Auth::id(),
            ]);

            $this->auditService->log('supplier_rating.created', 'supplier_rating', $rating->id, null, $rating->toArray(), tenantId: $tenantId);

            return $rating;
        });
    }

    public function updateRating(SupplierRating $rating, array $data): SupplierRating
    {
        $tenantId = $rating->tenant_id;

        return DB::transaction(function () use ($rating, $data, $tenantId) {
            $old = $rating->toArray();
            $rating->update([
                'rating' => $data['rating'] ?? $rating->rating,
                'criteria' => $data['criteria'] ?? $rating->criteria,
                'note' => $data['note'] ?? $rating->note,
            ]);

            $this->auditService->log('supplier_rating.updated', 'supplier_rating', $rating->id, $old, $rating->fresh()->toArray(), tenantId: $tenantId);

            return $rating->fresh();
        });
    }

    public function deleteRating(SupplierRating $rating): void
    {
        $tenantId = $rating->tenant_id;

        DB::transaction(function () use ($rating, $tenantId) {
            $old = $rating->toArray();
            $rating->delete();

            $this->auditService->log('supplier_rating.deleted', 'supplier_rating', $old['id'], $old, null, tenantId: $tenantId);
        });
    }

    public function getAverageRating(Supplier $supplier): array
    {
        $ratings = SupplierRating::where('tenant_id', $supplier->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->get();

        if ($ratings->isEmpty()) {
            return ['average_rating' => 0, 'rating_count' => 0];
        }

        return [
            'average_rating' => round($ratings->avg('rating'), 2),
            'rating_count' => $ratings->count(),
        ];
    }
}
