<?php
// app/Jobs/ImportImagesJob.php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\ProductImageImportStorage;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportImagesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $images;
    protected string $importDirectory;
    public $timeout = 600;

    public function __construct(array $images, string $importDirectory)
    {
        $this->images = $images;
        $this->importDirectory = $importDirectory;
    }

    public function handle(ProductImageImportStorage $importStorage): void
    {
        try {
            if ($this->batch() && $this->batch()->cancelled()) {
                return;
            }

            $imported = 0;
            $failed = 0;
            $errors = [];

            foreach ($this->images as $image) {
                $filename = (string) ($image['filename'] ?? 'Неизвестный файл');

                try {
                    $productId = $image['product_id'] ?? null;
                    $filePath = (string) ($image['file'] ?? '');
                    $type = $image['type'] ?? null;
                
                    if (
                        !$productId
                        || !in_array($type, ['main', 'background'], true)
                        || !$importStorage->fileExists(
                            $this->importDirectory,
                            $filePath
                        )
                    ) {
                        $failed++;
                        $errors[] = "{$filename}: товар не найден, тип некорректен или файл отсутствует";
                        continue;
                    }
                
                    $productFolder = "products/{$productId}";
                    if (!Storage::disk('public')->exists($productFolder)) {
                        Storage::disk('public')->makeDirectory($productFolder);
                    }
                
                    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $newFilename = Str::uuid()->toString() . '.' . $extension;
                    $newPath = $productFolder . '/' . $newFilename;
                
                    $content = $importStorage->contents(
                        $this->importDirectory,
                        $filePath
                    );
                    if (!Storage::disk('public')->put($newPath, $content)) {
                        throw new RuntimeException('Не удалось сохранить изображение');
                    }
                
                    try {
                        ProductImage::create([
                            'product_id' => $productId,
                            'path' => $newPath,
                            'disk' => 'public',
                            'is_main' => $type === 'main',
                            'is_background' => $type === 'background',
                            'sort_order' => 0,
                        ]);
                    } catch (Throwable $exception) {
                        Storage::disk('public')->delete($newPath);
                        throw $exception;
                    }
                
                    $imported++;
                
                } catch (Throwable $exception) {
                    $failed++;
                    $errors[] = "{$filename}: " . $exception->getMessage();
                    Log::error('Import image error', [
                        'filename' => $filename,
                        'exception' => $exception,
                    ]);
                }
            }
        
            $batchId = $this->batch() ? $this->batch()->id : null;
        
            if ($batchId) {
                Cache::put('import_images_result_' . $batchId, [
                    'imported' => $imported,
                    'failed' => $failed,
                    'errors' => $errors,
                ], now()->addHour());
            }
        } finally {
            $this->cleanup($importStorage);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->cleanup(app(ProductImageImportStorage::class));
    }

    private function cleanup(ProductImageImportStorage $importStorage): void
    {
        try {
            $importStorage->deleteDirectory($this->importDirectory);
        } catch (Throwable $exception) {
            Log::warning('Product image import directory cleanup failed', [
                'directory' => $this->importDirectory,
                'exception' => $exception,
            ]);
        }
    }
}
