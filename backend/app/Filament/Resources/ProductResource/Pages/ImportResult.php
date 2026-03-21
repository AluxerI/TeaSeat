<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\Page;

class ImportResult extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.product-resource.pages.import-result';

    protected static ?string $title = 'Результат импорта';
    protected static bool $shouldRegisterNavigation = false;

    public ?string $batchId = null;
    public ?array $result = null;

    public function mount($batchId): void
    {
        $this->batchId = $batchId;
        $this->result = cache()->get('import_result_' . $batchId);
        if (!$this->result) {
            $this->redirect(route('filament.admin.resources.products.index'));
        }
    }
}