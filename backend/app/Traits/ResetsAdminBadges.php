<?php

namespace App\Traits;

use App\Services\AdminBadgeService;

trait ResetsAdminBadges
{
    /**
     * Сбрасывает кеш бейджей при изменениях
     */
    protected static function bootResetsAdminBadges()
    {
        static::saved(function () {
            AdminBadgeService::clearCache();
        });

        static::deleted(function () {
            AdminBadgeService::clearCache();
        });
    }
}