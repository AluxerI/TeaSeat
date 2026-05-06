<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    /**
     * Заголовок страницы
     */
    protected static ?string $title = 'Создать промокод';

    /**
     * Действия после создания записи
     */
    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Можно добавить логику после создания
        // Например: отправить уведомление, очистить кеш, etc.
        
        // Очищаем кеш бейджей (чтобы обновился счётчик промокодов)
        \App\Services\AdminBadgeService::clearCache();
    }

    /**
     * Перенаправление после создания
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Заголовок уведомления об успешном создании
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Промокод успешно создан';
    }

    /**
     * Обработка данных перед сохранением
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Приводим код к верхнему регистру
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        
        // type уже передан из формы (hidden поле)
        // но можно установить явно
        $data['type'] = 'cart'; // или shipping, в зависимости от формы
        
        return $data;
    }
}