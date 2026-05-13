<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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
            'manage orders'
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $adminRole = Role::findOrCreate('admin');
        $managerRole = Role::findOrCreate('manager');
        $userRole = Role::findOrCreate('user');

        $adminRole->givePermissionTo(Permission::all());

        $managerRole->givePermissionTo([
            'view products',
            'create products',
            'edit products',
            'view orders',
            'edit orders',
            'manage orders'
        ]);

        $userRole->givePermissionTo([
            'view products',
            'create orders',
            'view orders'
        ]);
    }
}
