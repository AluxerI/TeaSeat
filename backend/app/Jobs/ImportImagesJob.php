<?php
// app/Jobs/ImportImagesJob.php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;

class ImportImagesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $images;
    public $timeout = 600;

    public function __construct(array $images)
    {
        $this->images = $images;
    }

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($this->images as $image) {
            try {
                $productId = $image['product_id'];
                $filePath = $image['file'];
                $type = $image['type'];
                
                if (!$productId || !file_exists($filePath)) {
                    $failed++;
                    $errors[] = "{$image['filename']}: товар не найден или файл отсутствует";
                    continue;
                }
                
                // Создаём папку для товара
                $productFolder = "products/{$productId}";
                if (!Storage::disk('public')->exists($productFolder)) {
                    Storage::disk('public')->makeDirectory($productFolder);
                }
                
                // Генерируем новое имя файла
                $extension = $image['extension'];
                $newFilename = time() . '_' . uniqid() . '.' . $extension;
                $newPath = $productFolder . '/' . $newFilename;
                
                // Копируем файл
                $content = file_get_contents($filePath);
                Storage::disk('public')->put($newPath, $content);
                
                // Создаём запись в базе
                ProductImage::create([
                    'product_id' => $productId,
                    'path' => $newPath,
                    'disk' => 'public',
                    'is_main' => $type === 'main',
                    'is_background' => $type === 'background',
                    'sort_order' => 0,
                ]);
                
                $imported++;
                
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "{$image['filename']}: " . $e->getMessage();
                Log::error('Import image error: ' . $e->getMessage());
            }
        }
        
        // ИСПРАВЛЕНО: используем batchId вместо jobId
        $batchId = $this->batch() ? $this->batch()->id : null;
        
        if ($batchId) {
            Cache::put('import_images_result_' . $batchId, [
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
            ], now()->addHour());
        }
    }
}