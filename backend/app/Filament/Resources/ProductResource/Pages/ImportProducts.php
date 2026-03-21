<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Jobs\ImportProductsJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Bus;

class ImportProducts extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.product-resource.pages.import-products';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square';
    protected static ?string $navigationGroup = 'Управление товарами';
    protected static ?string $navigationLabel = 'Импорт товаров';
    protected static ?string $title = 'Импорт товаров из Excel';

    public ?string $file = null;
    public ?string $batchId = null;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Excel файл')
                    ->required()
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                    ->maxSize(10240) // 10 MB
                    ->disk('local')
                    ->directory('imports')
                    ->preserveFilenames()
                    ->helperText('Загрузите файл в формате Excel (.xlsx, .xls) с колонками: Название, Цена, Категория, Бренд, Страна бренда, Вес (г), Описание, Ингредиенты, Остаток'),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $this->validate();

        $path = storage_path('app/' . $this->file);

        // Запускаем импорт в очереди с использованием пакета batching (Laravel 8+)
        // $batch = Bus::batch([
        //     new ImportProductsJob($path),
        // ])->dispatch();

        $this->batchId = $batch->id;

        Notification::make()
            ->title('Импорт запущен')
            ->body('Импорт товаров начат. Вы получите уведомление о завершении.')
            ->success()
            ->send();

        // Перенаправляем на страницу прогресса
        $this->redirect(route('filament.admin.resources.products.import-progress', ['batchId' => $batch->id]));
    }

    public function getFormSchema(): array
    {
        return $this->form->getSchema();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('import')
                ->label('Импортировать')
                ->submit('import')
                ->color('primary'),
        ];
    }
}