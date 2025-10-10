<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ForceDeleteUser extends Command
{
    protected $signature = 'user:force-delete {email}';
    protected $description = 'Окончательно удаляет пользователя по email';

    public function handle()
    {
        $email = $this->argument('email');
        
        // Ищем пользователя среди мягко удаленных
        $user = User::withTrashed()->where('email', $email)->first();
        
        if (!$user) {
            $this->error("Пользователь с email {$email} не найден.");
            return;
        }
        
        if (!$user->trashed()) {
            $this->error("Пользователь не был мягко удален. Сначала удалите аккаунт обычным способом.");
            return;
        }
        
        if ($this->confirm("Вы уверены, что хотите навсегда удалить пользователя {$user->name} ({$user->email})?")) {
            $user->forceDelete();
            $this->info("Пользователь {$user->email} окончательно удален.");
        }
    }
}
