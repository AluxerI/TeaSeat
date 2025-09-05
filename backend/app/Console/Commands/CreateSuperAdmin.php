<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    protected $signature = 'make:super-admin 
                            {name : The name of the super admin}
                            {email : The email of the super admin}
                            {password : The password for the super admin}
                            {--D|delete : Delete the super user instead of creating}
                            {--T|token : Create API token for the user}';
    
    protected $description = 'Create or delete a super admin user with admin role';

    public function handle()
    {
        if ($this->option('delete')) {
            return $this->deleteSuperAdmin();
        }

        return $this->createSuperAdmin();
    }

    protected function createSuperAdmin()
    {
        // Проверяем, существует ли уже пользователь с таким email
        if (User::where('email', $this->argument('email'))->exists()) {
            $this->error('User with this email already exists!');
            return 1;
        }

        // Создаем пользователя
        $user = User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => bcrypt($this->argument('password'))
        ]);

        // Назначаем роль admin
        $adminRole = Role::where('name', 'admin')->first();
        
        if (!$adminRole) {
            $this->error('Admin role not found! Run permission seeder first.');
            return 1;
        }

        $user->assignRole($adminRole);
        
        $this->info('Super admin created successfully!');
        $this->info('Name: ' . $user->name);
        $this->info('Email: ' . $user->email);
        $this->info('Role: admin');

        // Создаем токен если запрошен
        if ($this->option('token')) {
            $token = $user->createToken('super-admin-token')->plainTextToken;
            $this->info('API Token: ' . $token);
            $this->warn('⚠️  Save this token securely! It will not be shown again.');
        }

        return 0;
    }

    protected function deleteSuperAdmin()
    {
        $email = $this->argument('email');
        
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found!");
            return 1;
        }

        // Проверяем, что пользователь действительно админ
        if (!$user->hasRole('admin')) {
            $this->error("User {$email} is not an admin!");
            return 1;
        }

        if ($this->confirm("Are you sure you want to delete super admin {$user->name} ({$user->email})?")) {
            // Удаляем все токены пользователя
            $user->tokens()->delete();
            
            // Удаляем пользователя
            $user->delete();
            
            $this->info("Super admin {$user->email} deleted successfully!");
            return 0;
        }

        $this->info('Deletion cancelled.');
        return 0;
    }
}
