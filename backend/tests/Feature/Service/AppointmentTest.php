<?php

namespace Tests\Feature\Service;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\ServiceCatalog;
use App\Models\StaffSchedule;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private Customer $customer;
    private User $staff;
    private ServiceCatalog $service;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $managerRole = Role::where('slug', 'manager')->first();

        $this->tenant = Tenant::create(['name' => 'P8 Service', 'slug' => 'p8-service']);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $managerRole->id,
            'name' => 'Manager',
            'email' => 'manager@p8service.com',
            'password' => 'password',
        ]);

        $this->staff = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $managerRole->id,
            'name' => 'Staff',
            'email' => 'staff@p8service.com',
            'password' => 'password',
        ]);

        $store = new Store;
        $store->tenant_id = $this->tenant->id;
        $store->name = 'Main';
        $store->code = 'MS01';
        $store->is_active = true;
        $store->save();
        $this->store = $store;

        Auth::login($this->manager);

        $this->customer = Customer::create([
            'name' => 'Jane',
            'is_active' => true,
        ]);

        $module = \App\Models\Module::where('slug', 'appointments')->firstOrFail();
        TenantModule::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'module_id' => $module->id],
            ['is_enabled' => true]
        )->update(['is_enabled' => true]);

        $this->token = $this->manager->createToken('test')->plainTextToken;

        $cat = Category::create([
            'name' => 'Services',
            'slug' => 'services-' . uniqid(),
        ]);

        $serviceProduct = Product::create([
            'category_id' => $cat->id,
            'name' => 'Haircut',
            'sku' => 'SVC-' . uniqid(),
            'barcode' => (string) uniqid(),
            'cost_price' => 0,
            'selling_price' => 50000,
            'unit' => 'service',
            'is_service' => true,
            'is_trackable' => false,
        ]);

        $this->service = ServiceCatalog::create([
            'product_id' => $serviceProduct->id,
            'duration_minutes' => 30,
            'is_recurring' => false,
        ]);
    }

    public function test_can_create_appointment(): void
    {
        $date = now()->addDay();
        $this->createSchedule($date->dayOfWeek, '09:00', '17:00');
        $payload = [
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->staff->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '10:00',
            'services' => [
                ['service_catalog_id' => $this->service->id, 'quantity' => 1],
            ],
        ];

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/appointments', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'appointment_date' => $date->toDateString(),
        ]);
    }

    public function test_double_booking_same_staff_rejected(): void
    {
        $date = now()->addDay();
        $this->createSchedule($date->dayOfWeek, '09:00', '17:00');
        $payload = [
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->staff->id,
            'appointment_date' => $date->toDateString(),
            'start_time' => '10:00',
            'services' => [
                ['service_catalog_id' => $this->service->id, 'quantity' => 1],
            ],
        ];

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/appointments', $payload)
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/appointments', $payload)
            ->assertStatus(422);
    }

    private function createSchedule(int $day, string $start, string $end): void
    {
        StaffSchedule::create([
            'user_id' => $this->staff->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'is_available' => true,
            'effective_from' => now()->subDay()->toDateString(),
        ]);
    }
}
