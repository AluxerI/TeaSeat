<?php

namespace App\Jobs;

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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    public $timeout = 600; // 10 минут
    public $failOnTimeout = false;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function handle(): void
    {
        $imported = 0;
        $updated = 0;
        $errors = [];
        $duplicates = 0;

        try {
            // Читаем файл
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Удаляем заголовок
            array_shift($rows);

            // Получаем склад по умолчанию (первый активный)
            $defaultWarehouse = Warehouse::where('is_active', true)->first();
            if (!$defaultWarehouse) {
                throw new \Exception('Нет активного склада для добавления остатков');
            }

            DB::transaction(function () use ($rows, &$imported, &$updated, &$errors, &$duplicates, $defaultWarehouse) {
                foreach ($rows as $index => $row) {
                    $rowNum = $index + 2; // +2 из-за удалённого заголовка и 0-индекса

                    try {
                        // Извлекаем данные
                        [
                            $name,
                            $price,
                            $categoryPath,
                            $brandName,
                            $brandCountry,
                            $weight,
                            $description,
                            $ingredients,
                            $stock
                        ] = array_pad($row, 9, null);

                        // Пропускаем пустые строки
                        if (empty($name) && empty($price)) {
                            continue;
                        }

                        // Обязательные поля
                        if (empty($name)) {
                            $errors[] = "Строка {$rowNum}: отсутствует название товара";
                            continue;
                        }
                        if (empty($price) || !is_numeric($price)) {
                            $errors[] = "Строка {$rowNum}: некорректная цена";
                            continue;
                        }

                        $price = (float) $price;

                        // --- Обработка бренда ---
                        $brand = null;
                        if (!empty($brandName)) {
                            $brand = Brand::firstOrCreate(
                                ['name' => trim($brandName)],
                                ['country' => $brandCountry ?? null]
                            );
                        }

                        // --- Обработка категорий ---
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
                                $categoryIds = [
                                    'sub_subcategory_id' => $subSubcategory->id,
                                ];
                            }
                        }

                        // --- Поиск существующего товара ---
                        $existingProduct = Product::where('name', trim($name))
                            ->when($brand, fn($q) => $q->where('brand_id', $brand->id))
                            ->first();

                        if ($existingProduct) {
                            // Обновляем цену до максимальной
                            $newPrice = max($existingProduct->price, $price);
                            if ($newPrice > $existingProduct->price) {
                                $existingProduct->update(['price' => $newPrice]);
                                $updated++;
                            } else {
                                $duplicates++;
                            }
                            $product = $existingProduct;
                        } else {
                            // Создаём новый товар
                            $product = Product::create([
                                'name' => trim($name),
                                'price' => $price,
                                'weight_grams' => !empty($weight) ? (int) $weight : null,
                                'description' => $description,
                                'ingredients' => $ingredients,
                                'brand_id' => $brand?->id,
                                'is_available' => true, // временно, будет пересчитано после добавления остатков
                            ]);
                            $imported++;
                        }

                        // Привязываем к под-подкатегории
                        if ($categoryIds) {
                            $product->sub_subcategories()->syncWithoutDetaching([$categoryIds['sub_subcategory_id']]);
                        }

                        // Добавляем остатки на склад
                        if (!empty($stock) && is_numeric($stock)) {
                            Inventory::updateOrCreate(
                                [
                                    'product_id' => $product->id,
                                    'warehouse_id' => $defaultWarehouse->id,
                                ],
                                [
                                    'quantity' => (int) $stock,
                                    'last_restock_date' => now(),
                                ]
                            );
                        }

                        // Обновляем кешированные поля товара
                        $product->updateCacheFields();

                    } catch (\Exception $e) {
                        Log::error("Import error at row {$rowNum}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                        $errors[] = "Строка {$rowNum}: " . $e->getMessage();
                    }
                }
            });

        } catch (\Exception $e) {
            Log::error('Import job failed: ' . $e->getMessage());
            $errors[] = 'Общая ошибка: ' . $e->getMessage();
        }

        // Сохраняем результат в кеш на 1 час
        Cache::put('import_result_' . $this->job->getJobId(), [
            'imported' => $imported,
            'updated' => $updated,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ], now()->addHour());
    }
}