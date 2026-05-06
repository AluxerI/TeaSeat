<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    /**
     * Заголовок страницы
     */
    protected static ?string $title = 'Редактировать промокод';

    /**
     * Действия в заголовке страницы
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Просмотр'),
            Actions\DeleteAction::make()
                ->label('Удалить')
                ->modalHeading('Удалить промокод')
                ->modalDescription('Вы уверены, что хотите удалить этот промокод?'),
        ];
    }

    /**
     * Действия после обновления записи
     */
    protected function afterSave(): void
    {
        // Очищаем кеш бейджей
        \App\Services\AdminBadgeService::clearCache();
    }

    /**
     * Перенаправление после сохранения
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Заголовок уведомления об успешном сохранении
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Промокод успешно обновлён';
    }

    /**
     * Обработка данных перед сохранением
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Приводим код к верхнему регистру
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        
        return $data;
    }

    /**
     * Блокировка изменения некоторых полей
     */
    protected function fillForm(): void
    {
        parent::fillForm();
        
        // Если промокод уже использовался, блокируем изменение кода
        if ($this->record->used_count > 0) {
            $this->form->getComponent('code')->disabled(true);
        }
    }
}