<?php
// app/Console/Commands/ClearCategoryIcons.php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearCategoryIcons extends Command
{
    protected $signature = 'categories:clear-icons';
    protected $description = 'Clear all category icons';

    public function handle()
    {
        if ($this->confirm('This will delete all category icons. Are you sure?')) {
            // Удаляем папку с иконками
            Storage::disk('public')->deleteDirectory('category-icons');
            
            // Очищаем поля icon в таблицах
            Category::query()->update(['icon' => null]);
            Subcategory::query()->update(['icon' => null]);
            Sub_Subcategory::query()->update(['icon' => null]);
            
            $this->info('All category icons have been cleared.');
        }
    }
}