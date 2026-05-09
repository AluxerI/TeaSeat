<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;

class ImportProductsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    public $timeout = 600;
    public $failOnTimeout = false;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $imported = 0;
        $updated = 0;
        $stockUpdated = 0;
        $errors = [];
        $duplicates = 0;

        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            array_shift($rows);

            // Супер-очистка строк
            $cleanString = function($str) {
                if (!is_string($str)) {
                    return $str;
                }
                // Удаляем BOM и невидимые символы
                $str = preg_replace('/^\xEF\xBB\xBF/', '', $str);
                // Конвертируем в UTF-8
                $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
                // Удаляем битые последовательности
                $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
                // Заменяем битые символы на пробел
                $str = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $str);
                // Убираем множественные пробелы
                $str = preg_replace('/\s+/', ' ', $str);
                return trim($str);
            };

            // Склад по умолчанию
            $defaultWarehouse = Warehouse::find(1);
            if (!$defaultWarehouse) {
                $defaultWarehouse = Warehouse::first();
                if (!$defaultWarehouse) {
                    throw new \Exception('Нет активного склада для добавления остатков');
                }
            }

            // Обрабатываем каждую строку с отдельным соединением
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                
                // Пропускаем заведомо проблемные строки с битыми символами
                $rawName = $row[0] ?? '';
                if (is_string($rawName) && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $rawName)) {
                    $errors[] = "Строка {$rowNum}: содержит битые символы, пропущена";
                    Log::warning("Skipping row {$rowNum} due to binary characters in name");
                    continue;
                }

                try {
                    // ПРИНУДИТЕЛЬНО РАЗРЫВАЕМ СОЕДИНЕНИЕ И СОЗДАЕМ НОВОЕ ДЛЯ КАЖДОЙ СТРОКИ
                    DB::disconnect();
                    DB::reconnect();
                    
                    DB::transaction(function() use ($row, $rowNum, $cleanString, $defaultWarehouse, &$imported, &$updated, &$stockUpdated, &$errors, &$duplicates) {
                        
                        // Применяем очистку
                        $name = $cleanString($row[0] ?? '');
                        $price = $row[1] ?? null;
                        $categoryPath = $cleanString($row[2] ?? '');
                        $brandName = $cleanString($row[3] ?? '');
                        $brandCountry = $cleanString($row[4] ?? '');
                        $weight = $cleanString($row[5] ?? '');
                        $description = $cleanString($row[6] ?? '');
                        $ingredients = $cleanString($row[7] ?? '');
                        $stockWeight = $row[8] ?? null;
                        $stockPieces = $row[9] ?? null;

                        // Пропускаем пустые
                        if (empty($name) && empty($price)) {
                            $errors[] = "Строка {$rowNum}: пустая строка, пропущена";
                            return;
                        }

                        // Валидация
                        if (empty($name)) {
                            $errors[] = "Строка {$rowNum}: отсутствует название товара";
                            return;
                        }
                        
                        if (empty($price) || !is_numeric($price) || $price <= 0) {
                            $errors[] = "Строка {$rowNum}: некорректная цена '{$price}'";
                            return;
                        }

                        // Обрезаем длинные поля
                        $name = mb_substr($name, 0, 255);
                        $description = mb_substr($description, 0, 10000);
                        $ingredients = mb_substr($ingredients, 0, 5000);
                        
                        // Дополнительная очистка от кавычек и спецсимволов
                        $description = str_replace(['"', "'", '`'], '"', $description);
                        $ingredients = str_replace(['"', "'", '`'], '"', $ingredients);

                        $price = (float) $price;
                        $weightGrams = !empty($weight) && is_numeric($weight) ? (int) $weight : null;

                        // --- Бренд ---
                        $brand = null;
                        if (!empty($brandName)) {
                            $brand = Brand::firstOrCreate(
                                ['name' => $brandName],
                                ['country' => $brandCountry ?? null]
                            );
                        }

                        // --- Категории ---
                        $categoryIds = null;
                        if (!empty($categoryPath)) {
                            $parts = explode('/', trim($categoryPath));
                            if (count($parts) >= 3) {
                                $category = Category::firstOrCreate(['name' => trim($parts[0])]);
                                $subcategory = Subcategory::firstOrCreate([
                                    'category_id' => $category->id,
                                    'name' => trim($parts[1])
                                ]);
                                $subSubcategory = Sub_Subcategory::firstOrCreate([
                                    'subcategory_id' => $subcategory->id,
                                    'name' => trim($parts[2])
                                ]);
                                $categoryIds = ['sub_subcategory_id' => $subSubcategory->id];
                            }
                        }

                        // --- Поиск существующего ---
                        $existingProduct = null;
                        if ($brand) {
                            $existingProduct = Product::where('name', $name)
                                ->where('brand_id', $brand->id)
                                ->first();
                        } else {
                            $existingProduct = Product::where('name', $name)->first();
                        }

                        if ($existingProduct) {
                            // Обновление
                            $updateData = [];
                            $priceChanged = false;

                            $newPrice = max($existingProduct->price, $price);
                            if ($newPrice > $existingProduct->price) {
                                $updateData['price'] = $newPrice;
                                $priceChanged = true;
                            }
                            
                            if (empty($existingProduct->weight_grams) && !empty($weightGrams)) {
                                $updateData['weight_grams'] = $weightGrams;
                            }
                            if (empty($existingProduct->description) && !empty($description)) {
                                $updateData['description'] = $description;
                            }
                            if (empty($existingProduct->ingredients) && !empty($ingredients)) {
                                $updateData['ingredients'] = $ingredients;
                            }
                            
                            if (!empty($updateData)) {
                                $existingProduct->update($updateData);
                                $updated++;
                            }
                            
                            $product = $existingProduct;
                            
                            // Остатки
                            $quantityToAdd = 0;
                            $weightToAdd = null;

                            if (!empty($stockPieces) && is_numeric($stockPieces) && $stockPieces > 0) {
                                $quantityToAdd = (int) $stockPieces;
                            } elseif (!empty($stockWeight) && is_numeric($stockWeight) && $stockWeight > 0) {
                                if ($weightGrams && $weightGrams > 0) {
                                    $quantityToAdd = floor($stockWeight / $weightGrams);
                                    $weightToAdd = $stockWeight - ($quantityToAdd * $weightGrams);
                                }
                            }

                            if ($quantityToAdd > 0) {
                                $inventory = Inventory::firstOrNew([
                                    'product_id' => $product->id,
                                    'warehouse_id' => $defaultWarehouse->id,
                                ]);
                                
                                $inventory->quantity = ($inventory->quantity ?? 0) + $quantityToAdd;
                                $inventory->weight_quantity = $weightToAdd;
                                $inventory->last_restock_date = now()->toDateString();
                                $inventory->save();
                                $stockUpdated++;
                            } elseif (!$priceChanged && empty($updateData)) {
                                $duplicates++;
                            }
                            
                        } else {
                            // Создание нового
                            $product = Product::create([
                                'name' => $name,
                                'price' => $price,
                                'weight_grams' => $weightGrams,
                                'description' => $description ?: null,
                                'ingredients' => $ingredients ?: null,
                                'brand_id' => $brand?->id,
                                'is_available' => true,
                            ]);
                            $imported++;

                            // Остатки для нового
                            $quantityToAdd = 0;
                            $weightToAdd = null;

                            if (!empty($stockWeight) && is_numeric($stockWeight) && $stockWeight > 0) {
                                if ($weightGrams && $weightGrams > 0) {
                                    $quantityToAdd = floor($stockWeight / $weightGrams);
                                    $weightToAdd = $stockWeight - ($quantityToAdd * $weightGrams);
                                }
                            } elseif (!empty($stockPieces) && is_numeric($stockPieces) && $stockPieces > 0) {
                                $quantityToAdd = (int) $stockPieces;
                            }

                            if ($quantityToAdd > 0) {
                                $inventory = Inventory::firstOrNew([
                                    'product_id' => $product->id,
                                    'warehouse_id' => $defaultWarehouse->id,
                                ]);

                                $inventory->quantity = ($inventory->quantity ?? 0) + $quantityToAdd;
                                $inventory->weight_quantity = $weightToAdd;
                                $inventory->last_restock_date = now()->toDateString();
                                $inventory->save();
                            }
                        }

                        // Привязываем категорию
                        if ($categoryIds && isset($product)) {
                            $product->sub_subcategories()->syncWithoutDetaching([$categoryIds['sub_subcategory_id']]);
                        }

                        // Обновляем кеш
                        if (isset($product)) {
                            $product->updateCacheFields();
                        }
                    });
                    
                } catch (\Exception $e) {
                    Log::error("Import error at row {$rowNum}: " . $e->getMessage());
                    $errors[] = "Строка {$rowNum}: " . $e->getMessage();
                    // Принудительно откатываем и пересоздаем соединение
                    DB::rollBack();
                    DB::disconnect();
                }
            }

        } catch (\Exception $e) {
            Log::error('Import job failed: ' . $e->getMessage());
            $errors[] = 'Общая ошибка: ' . $e->getMessage();
        }
        
        // Сохраняем результат
        $batchId = $this->batch() ? $this->batch()->id : null;
        
        $result = [
            'imported' => $imported,
            'updated' => $updated,
            'stock_updated' => $stockUpdated,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
        
        if ($batchId) {
            Log::info('Saving import result', ['batch_id' => $batchId, 'result' => $result]);
            Cache::put('import_result_' . $batchId, $result, now()->addHour());
        }
    }
}