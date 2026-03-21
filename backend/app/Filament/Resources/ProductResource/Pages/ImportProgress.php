<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Bus;

class ImportProgress extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.product-resource.pages.import-progress';

    protected static ?string $title = 'Прогресс импорта';
    protected static bool $shouldRegisterNavigation = false;

    public ?string $batchId = null;
    public ?array $result = null;

    public function mount($batchId): void
    {
        $this->batchId = $batchId;
        $this->result = cache()->get('import_result_' . $batchId);
    }

    public function getBatch()
    {
        if (!$this->batchId) {
            return null;
        }
        return Bus::findBatch($this->batchId);
    }

    public function getResult()
    {
        return $this->result;
    }

    public function checkProgress(): void
    {
        $batch = $this->getBatch();
        if ($batch && $batch->finished()) {
            $this->result = cache()->get('import_result_' . $this->batchId);
            $this->redirect(route('filament.admin.resources.products.import-result', ['batchId' => $this->batchId]));
        }
    }
}