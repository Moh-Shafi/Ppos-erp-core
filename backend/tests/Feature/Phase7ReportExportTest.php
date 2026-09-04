<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $plan = Plan::first() ?? Plan::create(['name' => 'Basic', 'slug' => 'basic']);

        $this->tenant = Tenant::create([
            'name' => 'Export Tenant',
            'slug' => Str::random(10),
            'plan_id' => $plan->id,
        ]);

        $owner = Role::where('slug', 'owner')->first();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $owner->id,
        ]);

        $this->actingAs($this->user);

        $this->store = new Store([
            'name' => 'Main Store',
            'code' => Str::random(10),
            'is_active' => true,
        ]);
        $this->store->tenant_id = $this->tenant->id;
        $this->store->save();
    }

    private function createSale(int $daysAgo): void
    {
        Sale::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'sale_number' => Str::random(10),
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'sale_date' => now()->subDays($daysAgo),
            'subtotal' => 100000,
            'discount' => 0,
            'tax' => 0,
            'total' => 100000,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);
    }

    public function test_unauthenticated_export_is_rejected(): void
    {
        $this->createSale(1);

        app('auth')->forgetGuards();

        $this->postJson('/api/v1/reports/export', [
            'report_id' => 'sales',
            'format' => 'csv',
            'filters' => [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
            ],
        ])->assertStatus(401);
    }

    public function test_unauthorized_export_is_rejected(): void
    {
        $this->createSale(1);

        $staff = Role::where('slug', 'staff')->first();
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $staff ? $staff->id : null,
        ]);

        $this->actingAs($otherUser)
            ->postJson('/api/v1/reports/export', [
                'report_id' => 'sales',
                'format' => 'csv',
                'filters' => [
                    'date_from' => now()->subWeek()->toDateString(),
                    'date_to' => now()->toDateString(),
                ],
            ])
            ->assertStatus(403);
    }

    public function test_csv_export_returns_expected_content(): void
    {
        $this->createSale(1);

        $response = $this->post('/api/v1/reports/export', [
            'report_id' => 'sales',
            'format' => 'csv',
            'filters' => [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
            ],
        ]);

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('sales.csv', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Total', $response->getContent());
        $this->assertStringContainsString('100000', $response->getContent());
    }

    public function test_xlsx_export_returns_expected_content_type(): void
    {
        $this->createSale(1);

        $response = $this->post('/api/v1/reports/export', [
            'report_id' => 'sales',
            'format' => 'xlsx',
            'filters' => [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('sales.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_pdf_export_returns_expected_content_type(): void
    {
        $this->createSale(1);

        $response = $this->post('/api/v1/reports/export', [
            'report_id' => 'sales',
            'format' => 'pdf',
            'filters' => [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('sales.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_csv_export_matches_json_dataset(): void
    {
        $this->createSale(2);

        $json = $this->getJson('/api/v1/reports/sales?date_from=' . now()->subWeek()->toDateString() . '&date_to=' . now()->toDateString())
            ->assertStatus(200)
            ->json();

        $csvResponse = $this->post('/api/v1/reports/export', [
            'report_id' => 'sales',
            'format' => 'csv',
            'filters' => [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
            ],
        ]);

        $lines = explode("\n", trim($csvResponse->getContent()));
        $this->assertCount(2, $lines); // header + 1 data row

        $headers = str_getcsv($lines[0]);
        $data = str_getcsv($lines[1]);
        $row = array_combine($headers, $data);

        $this->assertSame((string) $json['data'][0]['total'], $row['Total']);
    }

    public function test_export_invalid_format_rejected(): void
    {
        $this->postJson('/api/v1/reports/export', [
            'report_id' => 'sales',
            'format' => 'xml',
        ])->assertStatus(422);
    }

    public function test_export_unregistered_report_rejected(): void
    {
        $this->postJson('/api/v1/reports/export', [
            'report_id' => 'unknown-report',
            'format' => 'csv',
        ])->assertStatus(400);
    }

    public function test_export_size_respects_per_page(): void
    {
        $this->createSale(1);
        $this->createSale(2);

        $oneRow = $this->post('/api/v1/reports/export', [
            'report_id' => 'sales',
            'format' => 'csv',
            'filters' => [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
                'per_page' => 1,
            ],
        ]);

        $this->assertCount(2, explode("\n", trim($oneRow->getContent())));

        $twoRows = $this->post('/api/v1/reports/export', [
            'report_id' => 'sales',
            'format' => 'csv',
            'filters' => [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
                'per_page' => 10,
            ],
        ]);

        $this->assertCount(3, explode("\n", trim($twoRows->getContent())));
    }
}
