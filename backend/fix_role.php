<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;

$user = User::where('email', 'admin@kasirpos.id')->first();
echo "User: {$user->id}\n";
echo "Current role_id: {$user->role_id}\n";

// Find owner role
$role = Role::where('slug', 'owner')->first();
if ($role) {
    $user->role_id = $role->id;
    $user->save();
    echo "Set role to: {$role->name} ({$role->slug})\n";
} else {
    echo "Owner role not found!\n";
    foreach (Role::all() as $r) {
        echo "  - {$r->id}: {$r->name} ({$r->slug})\n";
    }
}

// Verify permissions
echo "Permissions: " . $user->role->permissions()->pluck('slug')->toJson() . "\n";
