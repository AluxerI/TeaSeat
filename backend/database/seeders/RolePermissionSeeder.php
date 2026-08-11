<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissions = [
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage users',
            'manage settings',
            'manage orders',
            'view inventory',
            'adjust inventory',
            'create seller orders',
            'view own seller orders',
            'complete own seller orders',
            'view picking orders',
            'manage own picking orders',
            'report picking shortage',
            'view assigned deliveries',
            'update assigned deliveries',
            'assign couriers',
            'view manager orders',
            'manage manager orders',
            'view fulfillment issues',
            'manage fulfillment issues',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => User::ROLE_ADMIN,
            'guard_name' => 'web',
        ]);
        $managerRole = Role::firstOrCreate([
            'name' => User::ROLE_MANAGER,
            'guard_name' => 'web',
        ]);
        $sellerRole = Role::firstOrCreate([
            'name' => User::ROLE_SELLER,
            'guard_name' => 'web',
        ]);
        $courierRole = Role::firstOrCreate([
            'name' => User::ROLE_COURIER,
            'guard_name' => 'web',
        ]);
        $pickerRole = Role::firstOrCreate([
            'name' => User::ROLE_PICKER,
            'guard_name' => 'web',
        ]);
        $userRole = Role::firstOrCreate([
            'name' => User::ROLE_USER,
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions(Permission::all());

        $managerRole->syncPermissions([
            'view products',
            'create products',
            'edit products',
            'view orders',
            'edit orders',
            'manage orders',
            'view inventory',
            'adjust inventory',
            'assign couriers',
            'view manager orders',
            'manage manager orders',
            'view fulfillment issues',
            'manage fulfillment issues',
        ]);

        $sellerRole->syncPermissions([
            'view products',
            'create seller orders',
            'view own seller orders',
            'complete own seller orders',
        ]);

        $pickerRole->syncPermissions([
            'view products',
            'view picking orders',
            'manage own picking orders',
            'report picking shortage',
        ]);

        $courierRole->syncPermissions([
            'view assigned deliveries',
            'update assigned deliveries',
        ]);

        $userRole->syncPermissions([
            'view products',
            'create orders',
            'view orders',
        ]);

        $registrar->forgetCachedPermissions();
    }
}
