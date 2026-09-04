<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tenantId = 1;
$storeId = DB::table('stores')->where('tenant_id', $tenantId)->value('id');
$userId = DB::table('users')->where('tenant_id', $tenantId)->value('id');

echo "Tenant: $tenantId | Store: $storeId | User: $userId\n";

// 1. Categories
$catId1 = DB::table('categories')->where('tenant_id', $tenantId)->where('slug', 'makanan')->value('id');
if (!$catId1) {
    $catId1 = DB::table('categories')->insertGetId([
        'tenant_id' => $tenantId, 'name' => 'Makanan', 'slug' => 'makanan',
        'is_active' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}
$catId2 = DB::table('categories')->where('tenant_id', $tenantId)->where('slug', 'minuman')->value('id');
if (!$catId2) {
    $catId2 = DB::table('categories')->insertGetId([
        'tenant_id' => $tenantId, 'name' => 'Minuman', 'slug' => 'minuman',
        'is_active' => 1, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
}
echo "Categories: $catId1, $catId2\n";

// 2. Unit
$unitId = DB::table('units')->where('tenant_id', $tenantId)->where('name', 'Porsi')->value('id');
if (!$unitId) {
    $unitId = DB::table('units')->insertGetId([
        'tenant_id' => $tenantId, 'name' => 'Porsi', 'symbol' => 'porsi',
        'is_base_unit' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}
echo "Unit: $unitId\n";

// 3. Products (schema: cost_price, selling_price, unit, is_trackable, min_stock)
$productsData = [
    ['name' => 'Nasi Goreng Spesial', 'sku' => 'NSG-001', 'selling_price' => 25000, 'cost_price' => 12000, 'category_id' => $catId1],
    ['name' => 'Es Teh Manis', 'sku' => 'ETM-002', 'selling_price' => 6000, 'cost_price' => 2000, 'category_id' => $catId2],
    ['name' => 'Ayam Bakar Madu', 'sku' => 'ABM-003', 'selling_price' => 30000, 'cost_price' => 15000, 'category_id' => $catId1],
    ['name' => 'Sate Ayam (10 tusuk)', 'sku' => 'SAT-004', 'selling_price' => 28000, 'cost_price' => 14000, 'category_id' => $catId1],
    ['name' => 'Kopi Susu Gula Aren', 'sku' => 'KSG-005', 'selling_price' => 18000, 'cost_price' => 7000, 'category_id' => $catId2],
    ['name' => 'Mie Goreng', 'sku' => 'MGR-006', 'selling_price' => 22000, 'cost_price' => 10000, 'category_id' => $catId1],
    ['name' => 'Es Jeruk', 'sku' => 'EJR-007', 'selling_price' => 8000, 'cost_price' => 2500, 'category_id' => $catId2],
    ['name' => 'Gado-Gado', 'sku' => 'GDO-008', 'selling_price' => 20000, 'cost_price' => 9000, 'category_id' => $catId1],
];

$productIds = [];
$productPrices = [];
foreach ($productsData as $pd) {
    $pid = DB::table('products')->where('tenant_id', $tenantId)->where('sku', $pd['sku'])->value('id');
    if (!$pid) {
        $pid = DB::table('products')->insertGetId([
            'tenant_id' => $tenantId, 'category_id' => $pd['category_id'],
            'name' => $pd['name'], 'sku' => $pd['sku'],
            'cost_price' => $pd['cost_price'], 'selling_price' => $pd['selling_price'],
            'unit' => 'porsi', 'is_active' => 1, 'is_trackable' => 1, 'min_stock' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $productIds[] = $pid;
    $productPrices[] = $pd['selling_price'];
}
echo "Products: " . count($productIds) . "\n";

// 4. Inventory
foreach ($productIds as $pid) {
    $exists = DB::table('inventories')->where('tenant_id', $tenantId)
        ->where('store_id', $storeId)->where('product_id', $pid)->exists();
    if (!$exists) {
        DB::table('inventories')->insert([
            'tenant_id' => $tenantId, 'store_id' => $storeId, 'product_id' => $pid,
            'quantity' => rand(5, 80), 'minimum_quantity' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
echo "Inventory created\n";

// 5. Customers
$customersData = [
    ['name' => 'Budi Santoso', 'phone' => '081234567890', 'email' => 'budi@example.com'],
    ['name' => 'Siti Aminah', 'phone' => '082345678901', 'email' => 'siti@example.com'],
    ['name' => 'Ahmad Wijaya', 'phone' => '083456789012', 'email' => 'ahmad@example.com'],
    ['name' => 'Dewi Lestari', 'phone' => '084567890123', 'email' => 'dewi@example.com'],
    ['name' => 'Rudi Hartono', 'phone' => '085678901234', 'email' => 'rudi@example.com'],
];

$customerIds = [];
foreach ($customersData as $cd) {
    $cid = DB::table('customers')->where('tenant_id', $tenantId)->where('phone', $cd['phone'])->value('id');
    if (!$cid) {
        $cid = DB::table('customers')->insertGetId(array_merge($cd, [
            'tenant_id' => $tenantId, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }
    $customerIds[] = $cid;
}
echo "Customers: " . count($customerIds) . "\n";

// 6. Generate sales over past 7 days
$paymentMethods = ['cash', 'qris', 'card', 'bank_transfer'];
$saleCount = DB::table('sales')->where('tenant_id', $tenantId)->count();

for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
    $date = now()->subDays($dayOffset);
    $numSales = rand(4, 14);

    for ($s = 0; $s < $numSales; $s++) {
        $saleDate = (clone $date)->addHours(rand(8, 21))->addMinutes(rand(0, 59));
        $numItems = rand(1, 4);
        $subtotal = 0;
        $items = [];

        for ($i = 0; $i < $numItems; $i++) {
            $pidx = array_rand($productIds);
            $pid = $productIds[$pidx];
            $price = $productPrices[$pidx];
            $name = $productsData[$pidx]['name'];
            $sku = $productsData[$pidx]['sku'];
            $qty = rand(1, 3);
            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;
            $items[] = ['product_id' => $pid, 'name' => $name, 'sku' => $sku, 'price' => $price, 'qty' => $qty, 'lineTotal' => $lineTotal];
        }

        $tax = round($subtotal * 0.11, 2);
        $total = $subtotal + $tax;
        $method = $paymentMethods[array_rand($paymentMethods)];
        $customerId = $customerIds[array_rand($customerIds)];
        $saleCount++;

        $saleId = DB::table('sales')->insertGetId([
            'tenant_id' => $tenantId, 'store_id' => $storeId, 'cashier_id' => $userId,
            'customer_id' => $customerId,
            'sale_number' => 'INV-' . str_pad($saleCount, 6, '0', STR_PAD_LEFT),
            'status' => 'completed', 'payment_status' => 'paid',
            'sale_date' => $saleDate, 'subtotal' => $subtotal, 'discount' => 0,
            'tax' => $tax, 'total' => $total, 'paid_amount' => $total, 'change_amount' => 0,
            'created_at' => $saleDate, 'updated_at' => $saleDate,
        ]);

        foreach ($items as $item) {
            DB::table('sale_items')->insert([
                'sale_id' => $saleId, 'product_id' => $item['product_id'],
                'product_name' => $item['name'], 'sku' => $item['sku'],
                'quantity' => $item['qty'], 'unit_price' => $item['price'],
                'original_price' => $item['price'], 'discount' => 0, 'tax' => 0,
                'subtotal' => $item['lineTotal'], 'total' => $item['lineTotal'],
                'created_at' => $saleDate, 'updated_at' => $saleDate,
            ]);
        }

        DB::table('payments')->insert([
            'tenant_id' => $tenantId, 'sale_id' => $saleId,
            'payment_method' => $method, 'amount' => $total, 'change_amount' => 0,
            'status' => 'success', 'payment_date' => $saleDate,
            'created_at' => $saleDate, 'updated_at' => $saleDate,
        ]);
    }
}

echo "Total sales: " . DB::table('sales')->where('tenant_id', $tenantId)->count() . "\n";
echo "Sale items: " . DB::table('sale_items')->whereIn('sale_id', DB::table('sales')->where('tenant_id', $tenantId)->pluck('id'))->count() . "\n";
echo "Payments: " . DB::table('payments')->where('tenant_id', $tenantId)->count() . "\n";

// Make one product low stock
DB::table('inventories')->where('tenant_id', $tenantId)->limit(1)->update(['quantity' => 3]);
echo "Set 1 product to low stock (qty=3)\n";

echo "DONE\n";
