<?php

namespace Tests\Unit;

use App\Services\ProductImportStorage;
use InvalidArgumentException;
use Tests\TestCase;

class ProductImportStorageTest extends TestCase
{
    public function test_it_creates_a_safe_relative_path_for_a_clean_file(): void
    {
        $storage = app(ProductImportStorage::class);
        $relativePath = $storage->newCleanFile('Шаблон товара.xlsx');

        $this->assertMatchesRegularExpression(
            '/^clean\/Шаблон_товара_clean_[0-9a-f-]{36}\.xlsx$/u',
            $relativePath
        );
        $this->assertSame(
            storage_path('app/imports/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath)),
            $storage->path($relativePath)
        );
    }

    public function test_it_rejects_a_path_outside_the_import_storage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ProductImportStorage::class)->path('../orders.xlsx');
    }
}
