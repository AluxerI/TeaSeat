<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class ImportProgress extends Page
{
    protected static string $resource = ProductResource::class;
    protected static string $view = 'filament.pages.import-progress';
    protected static ?string $title = 'Прогресс импорта';
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
                $this->errorMessage = 'Пакет импорта не найден. Возможно, он был удалён или не создался.';
                return;
            }
            
            $this->progress = $batch->progress();
            $this->isFinished = $batch->finished();
            
            // Проверка на ошибки в batch
            if ($batch->hasFailures()) {
                $this->hasError = true;
                $this->errorMessage = 'Импорт завершился с ошибками. Проверьте лог worker.';
                // Можно собрать ошибки из $batch->failedJobs
            }
            
            if ($this->isFinished) {
                $this->result = Cache::get('import_result_' . $this->batchId);
                if (!$this->result) {
                    // Если результа нет в кеше, но batch завершён – возможно, ошибка
                    $this->hasError = true;
                    $this->errorMessage = 'Результат импорта не найден в кеше. Возможно, job не сохранил результат.';
                }
            }
        } catch (\Exception $e) {
            $this->hasError = true;
            $this->errorMessage = 'Ошибка при проверке прогресса: ' . $e->getMessage();
            \Log::error('ImportProgress error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

    public function goToResult(): void
    {
        $this->redirect(route('filament.admin.resources.products.import-result', ['batchId' => $this->batchId]));
    }

    public function getBatch()
    {
        return Bus::findBatch($this->batchId);
    }
}