<?php
// app/Filament/Resources/ProductResource/Pages/ImportImagesProgress.php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class ImportImagesProgress extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.pages.import-images-progress';

    protected static ?string $title = 'Прогресс импорта изображений';
    protected static bool $shouldRegisterNavigation = false;

    public ?string $batchId = null;
    public ?array $result = null;
    public int $progress = 0;
    public bool $isFinished = false;
    public bool $hasError = false;
    public ?string $errorMessage = null;

    public function mount($batchId): void
    {
        $this->batchId = $batchId;
        $this->checkProgress();
    }

    public function checkProgress(): void
    {
        try {
            $batch = Bus::findBatch($this->batchId);
            
            if (!$batch) {
                $this->hasError = true;
                $this->errorMessage = 'Пакет импорта не найден';
                return;
            }
            
            $this->progress = $batch->progress();
            $this->isFinished = $batch->finished();
            
            if ($this->isFinished) {
                // ИСПРАВЛЕНО: используем batchId для получения результата
                $this->result = Cache::get('import_images_result_' . $this->batchId);
                
                if (!$this->result) {
                    $this->hasError = true;
                    $this->errorMessage = 'Результат импорта не найден в кеше';
                }
            }
        } catch (\Exception $e) {
            $this->hasError = true;
            $this->errorMessage = 'Ошибка: ' . $e->getMessage();
            Log::error('ImportImagesProgress error: ' . $e->getMessage());
        }
    }

    public function goToResult(): void
    {
        $this->redirect(route('filament.admin.resources.products.import-images-result', ['batchId' => $this->batchId]));
    }

    public function getBatch()
    {
        return Bus::findBatch($this->batchId);
    }
}