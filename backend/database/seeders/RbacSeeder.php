<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Owner', 'slug' => 'owner'],
            ['name' => 'Manager', 'slug' => 'manager'],
            ['name' => 'Cashier', 'slug' => 'cashier'],
            ['name' => 'Staff', 'slug' => 'staff'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        $permissions = [
            ['name' => 'Manage Products', 'slug' => 'products.manage'],
            ['name' => 'View Products', 'slug' => 'products.view'],
            ['name' => 'Manage Sales', 'slug' => 'sales.manage'],
            ['name' => 'View Sales', 'slug' => 'sales.view'],
            ['name' => 'Manage Inventory', 'slug' => 'inventory.manage'],
            ['name' => 'View Inventory', 'slug' => 'inventory.view'],
            ['name' => 'Manage Customers', 'slug' => 'customers.manage'],
            ['name' => 'View Reports', 'slug' => 'reports.view'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage'],
            ['name' => 'Manage Users', 'slug' => 'users.manage'],
            ['name' => 'Use POS', 'slug' => 'pos.use'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $owner = Role::where('slug', 'owner')->first();
        $owner->permissions()->sync(Permission::all()->pluck('id'));

        $manager = Role::where('slug', 'manager')->first();
        $manager->permissions()->sync(
            Permission::whereIn('slug', [
                'products.view', 'products.manage',
                'sales.view', 'sales.manage',
                'inventory.view', 'inventory.manage',
                'customers.manage',
                'reports.view',
                'pos.use',
            ])->pluck('id')
        );

        $cashier = Role::where('slug', 'cashier')->first();
        $cashier->permissions()->sync(
            Permission::whereIn('slug', [
                'products.view',
                'sales.view', 'sales.manage',
                'customers.manage',
                'pos.use',
            ])->pluck('id')
        );

        $staff = Role::where('slug', 'staff')->first();
        $staff->permissions()->sync(
            Permission::whereIn('slug', [
                'products.view',
                'inventory.view',
            ])->pluck('id')
        );
    }
}
