<?php

namespace Tests\Feature;

class Phase4ReceiptSettingsTest extends Phase4TestHelper
{
    public function test_get_receipt_settings_default_null(): void
    {
        $this->setupPhase4();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->getJson("/api/v1/stores/{$this->store->id}/receipt-settings");

        $response->assertStatus(200);
        // Receipt settings default to null (may come as empty array in JSON response)
        $this->assertTrue($response->json() === null || $response->json() === []);
    }

    public function test_update_receipt_settings(): void
    {
        $this->setupPhase4();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->putJson("/api/v1/stores/{$this->store->id}/receipt-settings", [
                'header_text' => 'Welcome to our store',
                'footer_text' => 'Thank you!',
                'show_cashier' => true,
                'show_customer' => false,
                'show_qr_code' => true,
                'paper_width' => '80mm',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('header_text', 'Welcome to our store');
        $response->assertJsonPath('footer_text', 'Thank you!');
        $response->assertJsonPath('show_cashier', true);
        $response->assertJsonPath('paper_width', '80mm');
    }

    public function test_get_receipt_settings_after_update(): void
    {
        $this->setupPhase4();

        $this->withHeaders($this->authHeader($this->tokenOwner))
            ->putJson("/api/v1/stores/{$this->store->id}/receipt-settings", [
                'header_text' => 'Test Header',
            ]);

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->getJson("/api/v1/stores/{$this->store->id}/receipt-settings");

        $response->assertStatus(200);
        $response->assertJsonPath('header_text', 'Test Header');
    }

    public function test_receipt_settings_unauthenticated_blocked(): void
    {
        $this->setupPhase4();

        $response = $this->getJson("/api/v1/stores/{$this->store->id}/receipt-settings");
        $response->assertStatus(401);
    }

    public function test_receipt_settings_cashier_no_permission(): void
    {
        $this->setupPhase4();

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->getJson("/api/v1/stores/{$this->store->id}/receipt-settings");

        $response->assertStatus(403);
    }
}
