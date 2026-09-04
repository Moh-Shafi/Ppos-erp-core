<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Services\SupplierRatingService;
use Illuminate\Http\Request;

class SupplierRatingController extends Controller
{
    public function __construct(
        private readonly SupplierRatingService $ratingService,
    ) {}

    public function index(int $supplierId, Request $request)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($this->ratingService->listRatings($supplier, $perPage));
    }

    public function store(int $supplierId, Request $request)
    {
        $supplier = Supplier::findOrFail($supplierId);

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'criteria' => 'required|in:quality,delivery,pricing,service,overall',
            'note' => 'nullable|string|max:2000',
        ]);

        $rating = $this->ratingService->createRating($supplier, $validated);

        return response()->json($rating, 201);
    }

    public function update(int $supplierId, int $ratingId, Request $request)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $rating = SupplierRating::where('supplier_id', $supplierId)->findOrFail($ratingId);

        $validated = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'criteria' => 'sometimes|in:quality,delivery,pricing,service,overall',
            'note' => 'nullable|string|max:2000',
        ]);

        $rating = $this->ratingService->updateRating($rating, $validated);

        return response()->json($rating);
    }

    public function destroy(int $supplierId, int $ratingId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $rating = SupplierRating::where('supplier_id', $supplierId)->findOrFail($ratingId);

        $this->ratingService->deleteRating($rating);

        return response()->json(['message' => 'Rating deleted']);
    }
}
