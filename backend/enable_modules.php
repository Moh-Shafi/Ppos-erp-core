<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Module;
use App\Models\TenantModule;
use App\Models\Feature;
use App\Models\TenantFeature;

$user = User::where('email', 'admin@kasirpos.id')->first();
if (!$user) {
    echo "User not found!\n";
    exit(1);
}

$tenantId = $user->tenant_id;
echo "Tenant: {$tenantId}\n";

// Enable all modules
$modules = Module::all();
echo "Found {$modules->count()} modules\n";

foreach ($modules as $module) {
    TenantModule::firstOrCreate(
        ['tenant_id' => $tenantId, 'module_id' => $module->id],
        ['is_enabled' => true, 'enabled_at' => now()]
    );
}
echo "Enabled all modules\n";

// Enable all features
$features = Feature::all();
echo "Found {$features->count()} features\n";

foreach ($features as $feature) {
    TenantFeature::firstOrCreate(
        ['tenant_id' => $tenantId, 'feature_id' => $feature->id],
        ['is_enabled' => true]
    );
}
echo "Enabled all features\n";

// Verify
$enabledModules = TenantModule::where('tenant_id', $tenantId)->where('is_enabled', true)->pluck('module_id');
$slugs = Module::whereIn('id', $enabledModules)->pluck('slug')->toArray();
echo "Enabled module slugs: " . implode(', ', $slugs) . "\n";

echo "Done!\n";
