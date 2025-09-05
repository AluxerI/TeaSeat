<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); // очистка кэша
        // Создание прав
        $permissions = [
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'manage users',
            'manage settings'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Создание ролей
        $adminRole = Role::create(['name' => 'admin']);
        $managerRole = Role::create(['name' => 'manager']);
        $userRole = Role::create(['name' => 'user']);

        // Назначение прав админу
        $adminRole->givePermissionTo(Permission::all());

        // Назначение прав менеджеру
        $managerRole->givePermissionTo([
            'view products',
            'create products',
            'edit products',
            'view orders',
            'edit orders'
        ]);

        // Назначение прав покупателю
        $userRole->givePermissionTo([
        'view products',
        'create orders',
        'view orders'
    ]);
    }
}
