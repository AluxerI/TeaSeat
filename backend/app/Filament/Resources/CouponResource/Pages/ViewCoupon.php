<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCoupon extends ViewRecord
{
    protected static string $resource = CouponResource::class;

    /**
     * Заголовок страницы
     */
    protected static ?string $title = 'Просмотр промокода';

    /**
     * Действия в заголовке страницы
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Редактировать')
                ->icon('heroicon-o-pencil'),
            Actions\DeleteAction::make()
                ->label('Удалить')
                ->modalHeading('Удалить промокод'),
        ];
    }

    /**
     * Дополнительные данные для отображения
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Можно добавить вычисляемые поля для отображения
        $data['is_expired'] = $this->record->end_date && $this->record->end_date->isPast();
        $data['days_remaining'] = $this->record->end_date 
            ? now()->diffInDays($this->record->end_date, false) 
            : null;
        
        return $data;
    }

    /**
     * Перенаправление после удаления
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}