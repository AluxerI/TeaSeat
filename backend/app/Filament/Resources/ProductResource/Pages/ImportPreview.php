<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Jobs\ImportProductsJob;
use App\Services\ProductImportStorage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPreview extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.pages.import-preview';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square';
    protected static ?string $navigationGroup = 'Управление товарами';
    protected static ?string $navigationLabel = 'Импорт товаров';
    protected static ?string $title = 'Импорт товаров из Excel';

    public ?array $data = [];
    public ?array $previewData = [];
    public ?array $selectedRows = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Excel файл')
                    ->required()
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('imports')
                    ->preserveFilenames()
                    ->helperText('Загрузите файл для предварительного просмотра'),
            ])
            ->statePath('data');
    }

    public function parseFile(): void
    {
        $this->validate();
    
        $fileData = $this->data['file'];
        
        $tempFile = $this->getTempFile($fileData);
        
        if (!$tempFile) {
            Notification::make()
                ->title('Ошибка')
                ->body('Не удалось получить файл')
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
    
        $importStorage = app(ProductImportStorage::class);
        $cleanRelativePath = $importStorage->newCleanFile(
            $tempFile->getClientOriginalName()
        );
        $importStorage->ensureParentDirectory($cleanRelativePath);
        $cleanFilePath = $importStorage->path($cleanRelativePath);
        
        // ОЧИЩАЕМ ФАЙЛ ПЕРЕД ИМПОРТОМ
        $cleaner = new \App\Services\ExcelCleanerService();
        $cleanResult = $cleaner->clean($tempPath, $cleanFilePath);
        
        if (!empty($cleanResult['errors'])) {
            Notification::make()
                ->title('Внимание: найдены проблемы в файле')
                ->body('Обнаружено ' . count($cleanResult['errors']) . ' ошибок. Невалидные строки будут пропущены.')
                ->warning()
                ->send();
            
            // Логируем ошибки для детального просмотра
            session()->put('import_validation_errors', $cleanResult['errors']);
        }
        
        if ($cleanResult['cleaned_rows'] === 0) {
            Notification::make()
                ->title('Ошибка')
                ->body('Нет валидных строк для импорта. Все строки содержат ошибки.')
                ->danger()
                ->send();
            return;
        }
        
        // Используем ОЧИЩЕННЫЙ файл для предпросмотра
        $this->previewData = $this->parseExcel($cleanFilePath);
        $this->selectedRows = array_keys($this->previewData);
        
        // В сессии и очереди храним только путь внутри общего import-volume.
        session()->put('import_temp_file', $cleanRelativePath);
        
        Notification::make()
            ->title('Файл загружен и очищен')
            ->body("Найдено {$cleanResult['cleaned_rows']} валидных строк. Пропущено {$cleanResult['skipped_rows']} строк с ошибками.")
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

    protected function parseExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        array_shift($rows);
        
        $result = [];
        foreach ($rows as $index => $row) {
            // ОЧИСТКА КАЖДОГО ПОЛЯ ОТ БИТЫХ UTF-8
            $cleanRow = array_map(function($cell) {
                if (is_string($cell)) {
                    $cell = mb_convert_encoding($cell, 'UTF-8', 'UTF-8');
                    $cell = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cell);
                    return trim($cell);
                }
                return $cell;
            }, $row);
            
            $name = trim($cleanRow[0] ?? '');
            $price = $cleanRow[1] ?? null;
            $categoryPath = $cleanRow[2] ?? '';
            $brandName = $cleanRow[3] ?? '';
            $brandCountry = $cleanRow[4] ?? '';
            $weightGrams = !empty($cleanRow[5]) ? (int) $cleanRow[5] : null;
            $description = $cleanRow[6] ?? '';
            $ingredients = $cleanRow[7] ?? '';
            $stockWeight = $cleanRow[8] ?? null;
            $stockPieces = $cleanRow[9] ?? null;
        
            if (empty($name) && empty($price)) {
                continue;
            }
            
            $existingProduct = \App\Models\Product::where('name', $name)->first();
            
            $computedPieces = 0;
            $remainderGrams = 0;
            $mismatch = false;
            
            if (!empty($stockWeight) && is_numeric($stockWeight) && $stockWeight > 0) {
                if ($weightGrams && $weightGrams > 0) {
                    $computedPieces = floor($stockWeight / $weightGrams);
                    $remainderGrams = $stockWeight - ($computedPieces * $weightGrams);
                    if (!empty($stockPieces) && is_numeric($stockPieces) && $stockPieces > 0) {
                        if ($computedPieces != $stockPieces) {
                            $mismatch = true;
                        }
                    }
                }
            } elseif (!empty($stockPieces) && is_numeric($stockPieces) && $stockPieces > 0) {
                $computedPieces = (int) $stockPieces;
            }
            
            $errors = $this->validateRow($name, $price, $existingProduct);
            if ($mismatch) {
                $errors[] = "Несоответствие: по весу получается {$computedPieces} шт., а указано {$stockPieces} шт.";
            }
            
            $result[] = [
                'row' => $index + 2,
                'name' => $name,
                'price' => $price,
                'category' => $categoryPath,  // ИСПРАВЛЕНО: было $category
                'brand' => $brandName,         // ИСПРАВЛЕНО: было $brand
                'brand_country' => $brandCountry,
                'weight' => $weightGrams,
                'description' => $description,
                'ingredients' => $ingredients,
                'stock_weight' => $stockWeight,
                'stock_pieces' => $stockPieces,
                'computed_pieces' => $computedPieces,
                'remainder_grams' => $remainderGrams,
                'errors' => $errors,
                'exists' => $existingProduct ? true : false,
                'existing_price' => $existingProduct ? $existingProduct->price : null,
            ];
        }
        
        return $result;
    }

    protected function validateRow($name, $price, $existingProduct): array
    {
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Название обязательно';
        }
        
        if (empty($price) || !is_numeric($price)) {
            $errors[] = 'Цена должна быть числом';
        }
        
        if ($existingProduct) {
            $errors[] = 'Товар уже существует (будет обновлена цена)';
        }
        
        return $errors;
    }

    public function toggleSelectRow($index): void
    {
        if (in_array($index, $this->selectedRows)) {
            $this->selectedRows = array_diff($this->selectedRows, [$index]);
        } else {
            $this->selectedRows[] = $index;
        }
        $this->selectedRows = array_values($this->selectedRows);
    }

    public function toggleSelectAll(): void
    {
        if (count($this->selectedRows) === count($this->previewData)) {
            $this->selectedRows = [];
        } else {
            $this->selectedRows = array_keys($this->previewData);
        }
    }

    public function confirmImport(): void
    {
        $relativePath = session()->get('import_temp_file');
        $importStorage = app(ProductImportStorage::class);

        try {
            $fileExists = is_string($relativePath)
                && $importStorage->exists($relativePath);
        } catch (InvalidArgumentException) {
            $fileExists = false;
            session()->forget('import_temp_file');
        }
        
        if (!$fileExists || !is_string($relativePath)) {
            Notification::make()
                ->title('Ошибка')
                ->body('Файл для импорта не найден')
                ->danger()
                ->send();
            return;
        }
        
        $selectedData = [];
        foreach ($this->selectedRows as $index) {
            if (isset($this->previewData[$index])) {
                $selectedData[] = $this->previewData[$index];
            }
        }
        
        if (empty($selectedData)) {
            Notification::make()
                ->title('Ошибка')
                ->body('Не выбрано ни одной строки для импорта')
                ->danger()
                ->send();
            return;
        }
        
        $this->createTempExcel(
            $selectedData,
            $importStorage->path($relativePath)
        );
        
        $batch = Bus::batch([
            new ImportProductsJob($relativePath),
        ])->dispatch();

        session()->forget('import_temp_file');
        
        Notification::make()
            ->title('Импорт запущен')
            ->body('Выбрано ' . count($this->selectedRows) . ' товаров для импорта')
            ->success()
            ->send();
        
        $this->redirect(route('filament.admin.resources.products.import-progress', ['batchId' => $batch->id]));
    }
    
    protected function createTempExcel(array $data, string $originalPath): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Название', 'Цена', 'Категория', 'Бренд', 'Страна бренда', 'Вес (г)', 'Описание', 'Ингредиенты', 'Остаток (г)', 'Остаток (шт)'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

        foreach ($headers as $idx => $header) {
            $sheet->setCellValue($columns[$idx] . '1', $header);
        }

        $rowNum = 2;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $rowNum, $item['name']);
            $sheet->setCellValue('B' . $rowNum, $item['price']);
            $sheet->setCellValue('C' . $rowNum, $item['category']);
            $sheet->setCellValue('D' . $rowNum, $item['brand']);
            $sheet->setCellValue('E' . $rowNum, $item['brand_country']);
            $sheet->setCellValue('F' . $rowNum, $item['weight']);
            $sheet->setCellValue('G' . $rowNum, $item['description']);
            $sheet->setCellValue('H' . $rowNum, $item['ingredients']);
            $sheet->setCellValue('I' . $rowNum, $item['stock_weight'] ?? '');
            $sheet->setCellValue('J' . $rowNum, $item['stock_pieces'] ?? '');
            $rowNum++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($originalPath);
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('parse')
                ->label('Загрузить и показать предпросмотр')
                ->submit('parseFile')
                ->color('primary'),
            \Filament\Actions\Action::make('confirm')
                ->label('Импортировать выбранные')
                ->action('confirmImport')
                ->color('success')
                ->visible(fn () => !empty($this->previewData)),
        ];
    }
}
