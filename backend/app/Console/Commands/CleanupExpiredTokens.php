<?php

namespace App\Console\Commands;

use App\Models\PersonalAccessToken;
use Illuminate\Console\Command;

class CleanupExpiredTokens extends Command
{
    protected $signature = 'tokens:cleanup {--days=30 : Удалять токены старше N дней}';
    protected $description = 'Очищает просроченные и неиспользуемые токены';

    public function handle()
    {
        $days = $this->option('days');
        
        $deletedCount = PersonalAccessToken::where(function ($query) use ($days) {
            // Токены с истекшим сроком действия
            $query->whereNotNull('expires_at')
                  ->where('expires_at', '<', now());
        })->orWhere(function ($query) use ($days) {
            // Токены без last_used_at старше N дней
            $query->whereNull('last_used_at')
                  ->where('created_at', '<', now()->subDays($days));
        })->orWhere(function ($query) use ($days) {
            // Токены неиспользуемые более N дней
            $query->whereNotNull('last_used_at')
                  ->where('last_used_at', '<', now()->subDays($days));
        })->delete();

        $this->info("Удалено {$deletedCount} устаревших токенов.");
    }
}