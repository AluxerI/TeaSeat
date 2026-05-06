<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Создаём администратора (created_by = 1)
        User::firstOrCreate(
            ['email' => 'admin@teaseat.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Создаём тестового пользователя
        User::firstOrCreate(
            ['email' => 'user@teaseat.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Создаём ещё несколько тестовых пользователей (если нужно)
        if (User::count() < 5) {
            User::factory()->count(3)->create();
        }
    }
}