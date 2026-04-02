<?php
// app/Filament/Resources/ProductResource/Pages/ImportImages.php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Jobs\ImportImagesJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Bus;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use ZipArchive;

class ImportImages extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.pages.import-images';

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Управление товарами';
    protected static ?string $navigationLabel = 'Импорт изображений';
    protected static ?string $title = 'Импорт изображений товаров';

    public ?array $data = [];
    public ?array $previewData = [];
    public ?array $selectedFiles = [];
    public ?string $importPath = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('images')
                    ->label('ZIP архив с изображениями')
                    ->required()
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->maxSize(51200)
                    ->disk('local')
                    ->directory('imports/images')
                    ->preserveFilenames()
                    ->multiple(false)
                    ->helperText('Формат имён: "Название товара_m.jpg" (главное), "Название товара_b.jpg" (фоновое). Поддерживаются форматы: jpg, jpeg, png, gif, svg, webp'),
            ])
            ->statePath('data');
    }

    public function parseArchive(): void
    {
        $this->validate();

        $fileData = $this->data['images'];
        
        $tempFile = $this->getTempFile($fileData);
        
        if (!$tempFile) {
            Notification::make()
                ->title('Ошибка')
                ->body('Не удалось получить архив')
                ->danger()
                ->send();
            return;
        }

        $tempPath = $tempFile->getRealPath();
        
        if (!$tempPath || !file_exists($tempPath)) {
            Notification::make()
                ->title('Ошибка')
                ->body('Временный файл не найден')
                ->danger()
                ->send();
            return;
        }

        // Создаём временную папку для распаковки
        $extractPath = storage_path('app/public/imports/images/temp/' . uniqid());
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0777, true);
        }
        
        // Распаковываем архив
        $zip = new ZipArchive();
        if ($zip->open($tempPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            Notification::make()
                ->title('Ошибка')
                ->body('Не удалось распаковать архив')
                ->danger()
                ->send();
            return;
        }
        
        // Анализируем файлы
        $this->previewData = $this->analyzeImages($extractPath);
        $this->selectedFiles = array_keys($this->previewData);
        $this->importPath = $extractPath;
        
        session()->put('import_images_path', $this->importPath);
        
        Notification::make()
            ->title('Архив загружен')
            ->body('Найдено ' . count($this->previewData) . ' изображений')
            ->success()
            ->send();
    }

    protected function getTempFile($fileData): ?TemporaryUploadedFile
    {
        if (is_array($fileData)) {
            foreach ($fileData as $item) {
                if ($item instanceof TemporaryUploadedFile) {
                    return $item;
                }
                if (is_array($item)) {
                    foreach ($item as $subItem) {
                        if ($subItem instanceof TemporaryUploadedFile) {
                            return $subItem;
                        }
                    }
                }
            }
        } elseif ($fileData instanceof TemporaryUploadedFile) {
            return $fileData;
        }
        
        return null;
    }

    protected function analyzeImages(string $path): array
    {
        $files = glob($path . '/*.{jpg,jpeg,png,gif,svg,webp}', GLOB_BRACE);
        $result = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Парсим имя файла
            $parts = pathinfo($filename);
            $name = $parts['filename'];
            $extension = $parts['extension'];
            
            // Определяем тип изображения
            $type = null;
            $productName = $name;
            
            if (str_ends_with($name, '_m')) {
                $type = 'main';
                $productName = substr($name, 0, -2);
            } elseif (str_ends_with($name, '_b')) {
                $type = 'background';
                $productName = substr($name, 0, -2);
            }
            
            // Ищем товар по названию
            $product = \App\Models\Product::where('name', $productName)->first();
            
            $result[] = [
                'file' => $file,
                'filename' => $filename,
                'product_name' => $productName,
                'type' => $type,
                'extension' => $extension,
                'exists' => $product ? true : false,
                'product_id' => $product ? $product->id : null,
                'errors' => $this->validateImage($productName, $type, $product),
            ];
        }
        
        return $result;
    }

    protected function validateImage($productName, $type, $product): array
    {
        $errors = [];
        
        if (empty($productName)) {
            $errors[] = 'Некорректное имя файла';
        }
        
        if (!$product) {
            $errors[] = 'Товар с таким названием не найден';
        }
        
        if (!$type) {
            $errors[] = 'Не указан тип изображения (_m или _b)';
        }
        
        return $errors;
    }

    public function toggleSelectFile($index): void
    {
        if (in_array($index, $this->selectedFiles)) {
            $this->selectedFiles = array_diff($this->selectedFiles, [$index]);
        } else {
            $this->selectedFiles[] = $index;
        }
        $this->selectedFiles = array_values($this->selectedFiles);
    }

    public function toggleSelectAll(): void
    {
        if (count($this->selectedFiles) === count($this->previewData)) {
            $this->selectedFiles = [];
        } else {
            $this->selectedFiles = array_keys($this->previewData);
        }
    }

    public function confirmImport(): void
    {
        $importPath = session()->get('import_images_path');
        
        if (!$importPath || !is_dir($importPath)) {
            Notification::make()
                ->title('Ошибка')
                ->body('Папка с изображениями не найдена')
                ->danger()
                ->send();
            return;
        }
        
        $selectedData = [];
        foreach ($this->selectedFiles as $index) {
            if (isset($this->previewData[$index])) {
                $selectedData[] = $this->previewData[$index];
            }
        }
        
        if (empty($selectedData)) {
            Notification::make()
                ->title('Ошибка')
                ->body('Не выбрано ни одного изображения для импорта')
                ->danger()
                ->send();
            return;
        }
        
        $batch = Bus::batch([
            new ImportImagesJob($selectedData),
        ])->dispatch();
        
        Notification::make()
            ->title('Импорт запущен')
            ->body('Выбрано ' . count($this->selectedFiles) . ' изображений для импорта')
            ->success()
            ->send();
        
        $this->redirect(route('filament.admin.resources.products.import-images-progress', ['batchId' => $batch->id]));
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('parse')
                ->label('Загрузить и показать предпросмотр')
                ->submit('parseArchive')
                ->color('primary'),
            \Filament\Actions\Action::make('confirm')
                ->label('Импортировать выбранные')
                ->action('confirmImport')
                ->color('success')
                ->visible(fn () => !empty($this->previewData)),
        ];
    }
}