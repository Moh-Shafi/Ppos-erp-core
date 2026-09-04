<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Store;
use App\Models\Module;
use App\Models\TenantModule;
use App\Models\Feature;
use App\Models\TenantFeature;

// Check if tenant exists
$tenant = Tenant::find(1);
if (!$tenant) {
    $tenant = Tenant::where('slug', 'super-admin')->first();
}
if (!$tenant) {
    $tenant = new Tenant();
    $tenant->name = 'Super Admin Tenant';
    $tenant->slug = 'super-admin-' . time();
    $tenant->save();
    echo "Tenant created: {$tenant->id}\n";
}

// Check if store exists
$store = Store::where('tenant_id', $tenant->id)->first();
if (!$store) {
    $store = new Store();
    $store->tenant_id = $tenant->id;
    $store->name = 'Toko Utama';
    $store->code = 'MAIN';
    $store->address = 'Jakarta';
    $store->is_active = true;
    $store->save();
    echo "Store created: {$store->id}\n";
}

// Check if user exists
$user = User::where('email', 'admin@kasirpos.id')->first();
if (!$user) {
    $user = User::create([
        'name' => 'Super Admin',
        'email' => 'admin@kasirpos.id',
        'password' => bcrypt('KasirPOS2026!'),
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);
    echo "User created: {$user->id}\n";
} else {
    $user->password = bcrypt('KasirPOS2026!');
    $user->save();
    echo "User password reset: {$user->id}\n";
}

// Attach role
$role = Role::where('slug', 'owner')->first();
if ($role && $user->role_id !== $role->id) {
    $user->role_id = $role->id;
    $user->save();
    echo "Role attached: {$role->name}\n";
}

echo "Done!\n";

// Enable all modules and features for this tenant
$modules = Module::all();
foreach ($modules as $module) {
    TenantModule::firstOrCreate(
        ['tenant_id' => $tenant->id, 'module_id' => $module->id],
        ['is_enabled' => true, 'enabled_at' => now()]
    );
}
echo "Enabled {$modules->count()} modules\n";

$features = Feature::all();
foreach ($features as $feature) {
    TenantFeature::firstOrCreate(
        ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
        ['is_enabled' => true]
    );
}
echo "Enabled {$features->count()} features\n";
