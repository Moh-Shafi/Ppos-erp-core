<?php

namespace Tests\Feature;

use App\Models\SupplierRating;
use App\Services\SupplierRatingService;
use Illuminate\Support\Facades\Auth;

class Phase3SupplierRatingTest extends Phase3TestHelper
{
    private SupplierRatingService $ratingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase3();
        $this->ratingService = new SupplierRatingService();
    }

    public function test_create_rating(): void
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();

        $rating = $this->ratingService->createRating($supplier, [
            'rating' => 4,
            'criteria' => 'quality',
            'note' => 'Good quality',
        ]);

        $this->assertEquals(4, $rating->rating);
        $this->assertEquals('quality', $rating->criteria);
    }

    public function test_update_rating(): void
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();
        $rating = $this->ratingService->createRating($supplier, [
            'rating' => 3,
            'criteria' => 'delivery',
        ]);

        $updated = $this->ratingService->updateRating($rating, ['rating' => 5]);

        $this->assertEquals(5, $updated->rating);
    }

    public function test_delete_rating(): void
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();
        $rating = $this->ratingService->createRating($supplier, [
            'rating' => 4,
            'criteria' => 'overall',
        ]);

        $this->ratingService->deleteRating($rating);

        $this->assertNull(SupplierRating::find($rating->id));
    }

    public function test_average_rating_calculation(): void
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();

        $this->ratingService->createRating($supplier, ['rating' => 4, 'criteria' => 'quality']);
        $this->ratingService->createRating($supplier, ['rating' => 5, 'criteria' => 'delivery']);
        $this->ratingService->createRating($supplier, ['rating' => 3, 'criteria' => 'service']);

        $avg = $this->ratingService->getAverageRating($supplier);

        $this->assertEquals(4.0, $avg['average_rating']);
        $this->assertEquals(3, $avg['rating_count']);
    }

    public function test_rating_api(): void
    {
        $supplier = $this->createSupplier();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson("/api/v1/suppliers/{$supplier->id}/ratings", [
                'rating' => 5,
                'criteria' => 'quality',
                'note' => 'Excellent',
            ]);

        $response->assertStatus(201);
        $response->assertJson(['rating' => 5]);
    }

    public function test_staff_cannot_rate_supplier(): void
    {
        $supplier = $this->createSupplier();

        $staffRole = \App\Models\Role::where('slug', 'staff')->first();
        $staff = \App\Models\User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $staffRole->id,
            'name' => 'Staff',
            'email' => 'staff@p3test.com',
            'password' => 'password',
        ]);
        $token = $staff->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson("/api/v1/suppliers/{$supplier->id}/ratings", [
                'rating' => 5,
                'criteria' => 'quality',
            ]);

        $response->assertStatus(403);
    }
}
