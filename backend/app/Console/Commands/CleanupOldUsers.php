<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldUsers extends Command
{
    protected $signature = 'users:cleanup {--months=1 : Количество месяцев для хранения}';
    protected $description = 'Окончательно удаляет пользователей, удаленных более N месяцев назад';

    public function handle()
    {
        $months = $this->option('months');
        
        $count = User::onlyTrashed()
            ->where('deleted_at', '<=', now()->subMonths($months))
            ->count();
            
        // Показываем предупреждение
        if (!$this->confirm("Будет удалено {$count} пользователей. Продолжить?")) {
            return;
        }
        
        $deletedCount = User::onlyTrashed()
            ->where('deleted_at', '<=', now()->subMonths($months))
            ->forceDelete();
            
        $this->info("Удалено {$deletedCount} пользователей.");
    }
}
