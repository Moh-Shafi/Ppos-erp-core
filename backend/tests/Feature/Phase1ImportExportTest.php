<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Phase1ImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Tenant $tenant;
    private Category $category;
    private CatalogService $catalogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $this->tenant = Tenant::create(['name' => 'Test Toko', 'slug' => 'test-toko']);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $ownerRole->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $this->category = Category::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);

        $this->catalogService = app(CatalogService::class);
    }

    public function test_import_products_create(): void
    {
        $csv = "name,category_name,sku,barcode,cost_price,selling_price,unit,is_active,description\n";
        $csv .= "Espresso,Beverages,ESP-01,899001,5000,15000,pcs,1,Single shot\n";
        $csv .= "Cappuccino,Beverages,CAP-01,899002,7000,20000,pcs,1,Classic cappuccino\n";

        $file = $this->createCsvFile($csv);

        $result = $this->catalogService->importProducts($file, $this->tenant->id);

        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEmpty($result['errors']);
        $this->assertDatabaseHas('products', ['name' => 'Espresso', 'sku' => 'ESP-01']);
        $this->assertDatabaseHas('products', ['name' => 'Cappuccino', 'sku' => 'CAP-01']);
    }

    public function test_import_products_update_existing(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Old Name',
            'sku' => 'ESP-01',
            'cost_price' => 3000,
            'selling_price' => 8000,
            'unit' => 'pcs',
        ]);

        $csv = "name,category_name,sku,barcode,cost_price,selling_price,unit,is_active,description\n";
        $csv .= "Espresso Updated,Beverages,ESP-01,899001,5000,15000,pcs,1,Updated\n";

        $file = $this->createCsvFile($csv);

        $result = $this->catalogService->importProducts($file, $this->tenant->id);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertDatabaseHas('products', ['name' => 'Espresso Updated', 'sku' => 'ESP-01']);
    }

    public function test_import_products_validation_error(): void
    {
        $csv = "name,category_name,sku,barcode,cost_price,selling_price,unit,is_active,description\n";
        $csv .= "Espresso,Beverages,ESP-01,899001,5000,15000,pcs,1,OK\n";
        $csv .= ",Beverages,MISSING-NAME,899002,5000,15000,pcs,1,Missing name\n";

        $file = $this->createCsvFile($csv);

        $result = $this->catalogService->importProducts($file, $this->tenant->id);

        $this->assertEquals(1, $result['created']);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals(3, $result['errors'][0]['row']);
    }

    public function test_import_products_category_not_found(): void
    {
        $csv = "name,category_name,sku,barcode,cost_price,selling_price,unit,is_active,description\n";
        $csv .= "Test,NonExistent,TEST-01,899001,5000,15000,pcs,1,Test\n";

        $file = $this->createCsvFile($csv);

        $result = $this->catalogService->importProducts($file, $this->tenant->id);

        $this->assertEquals(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Category', $result['errors'][0]['error']);
    }

    public function test_export_products_csv(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Espresso',
            'sku' => 'ESP-01',
            'cost_price' => 5000,
            'selling_price' => 15000,
            'unit' => 'pcs',
        ]);

        $csv = $this->catalogService->exportProducts($this->tenant->id);

        $this->assertStringContainsString('name', $csv);
        $this->assertStringContainsString('Espresso', $csv);
        $this->assertStringContainsString('ESP-01', $csv);
    }

    public function test_import_csv_sanitizes_formula_injection(): void
    {
        $csv = "name,category_name,sku,barcode,cost_price,selling_price,unit,is_active,description\n";
        $csv .= "=cmd(),Beverages,INJ-01,899001,5000,15000,pcs,1,Formula injection\n";

        $file = $this->createCsvFile($csv);

        $result = $this->catalogService->importProducts($file, $this->tenant->id);

        $product = Product::where('sku', 'INJ-01')->first();
        $this->assertNotNull($product);
        $this->assertStringStartsWith("'", $product->name);
    }

    public function test_api_import_endpoint(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $csv = "name,category_name,sku,barcode,cost_price,selling_price,unit,is_active,description\n";
        $csv .= "Espresso,Beverages,ESP-01,899001,5000,15000,pcs,1,Single shot\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $response = $this->postJson('/api/v1/products/import', ['file' => $file], ['Content-Type' => 'multipart/form-data']);

        $response->assertStatus(200);
        $response->assertJsonPath('created', 1);
    }

    public function test_api_export_endpoint(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test',
            'sku' => 'TEST-01',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
        ]);

        $response = $this->getJson('/api/v1/products/export');

        $response->assertStatus(200);
        $response->assertHeaderContains('Content-Type', 'text/csv');
    }

    private function createCsvFile(string $content): UploadedFile
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tmpFile, $content);

        return new UploadedFile(
            $tmpFile,
            'test.csv',
            'text/csv',
            null,
            true
        );
    }
}
