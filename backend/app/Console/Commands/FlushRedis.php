<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FlushRedis extends Command
{
    protected $signature = 'cache:clear';
    protected $description = 'Clear Redis cache';

    public function handle()
    {
        Redis::connection()->flushdb();
        $this->info('Redis cache cleared successfully!');
    }
}
