<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait ClearsModelCache
{
    /**
     * Очищает кеш модели
     */
    public function clearCache(): void
    {
        $keys = $this->getCacheKeys();
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Базовые ключи кеша - переопределяется в модели
     */
    protected function getCacheKeys(): array
    {
        $modelName = class_basename($this);
        return [
            strtolower($modelName) . ".{$this->id}",
            strtolower($modelName) . "s.list",
        ];
    }

    /**
     * Очищает кеш при сохранении
     */
    protected static function bootClearsModelCache()
    {
        static::saved(function ($model) {
            $model->clearCache();
        });

        static::deleted(function ($model) {
            $model->clearCache();
        });
    }
}