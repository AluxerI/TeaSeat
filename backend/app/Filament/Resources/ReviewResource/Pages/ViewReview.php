<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewReview extends ViewRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            
            Actions\Action::make('view_product')
                ->label('Перейти к товару')
                ->icon('heroicon-o-cube')
                ->url(fn () => route('filament.admin.resources.products.view', [
                    'record' => $this->record->product_id
                ]))
                ->openUrlInNewTab(),
            
            Actions\Action::make('view_user')
                ->label('Перейти к пользователю')
                ->icon('heroicon-o-user')
                ->url(fn () => route('filament.admin.resources.users.view', [
                    'record' => $this->record->user_id
                ]))
                ->openUrlInNewTab(),
        ];
    }

    // ИСПРАВЛЕНО: изменен тип возвращаемого значения
    protected function resolveRecord(int | string $key): Model
    {
        $parts = explode('-', (string) $key);
        if (count($parts) === 2) {
            return Review::where('product_id', $parts[0])
                ->where('user_id', $parts[1])
                ->firstOrFail();
        }
        
        return parent::resolveRecord($key);
    }
}