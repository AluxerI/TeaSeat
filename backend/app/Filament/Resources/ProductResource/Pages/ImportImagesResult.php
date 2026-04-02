<?php
// app/Filament/Resources/ProductResource/Pages/ImportImagesResult.php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Filament\Notifications\Notification;

class ImportImagesResult extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.pages.import-images-result';

    protected static ?string $title = 'Результат импорта изображений';
    protected static bool $shouldRegisterNavigation = false;

    public ?string $batchId = null;
    public ?array $result = null;

    public function mount($batchId): void
    {
        $this->batchId = $batchId;
        
        // ИСПРАВЛЕНО: используем batchId для получения результата
        $this->result = Cache::get('import_images_result_' . $batchId);
        
        if (!$this->result || !is_array($this->result)) {
            Notification::make()
                ->title('Результат не найден')
                ->body('Возможно, импорт не был завершён или произошла ошибка.')
                ->warning()
                ->send();
            $this->redirect(route('filament.admin.resources.products.index'));
        }
    }
}