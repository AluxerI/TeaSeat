<?php

namespace Tests\Unit;

use App\Services\ProductImageImportStorage;
use DomainException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ProductImageImportStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_extracts_images_to_relative_public_storage_paths(): void
    {
        $storage = app(ProductImageImportStorage::class);
        $directory = $storage->newDirectory();
        $archive = $this->archiveWith([
            'Каталог/Чай_m.jpg' => 'image-content',
            'notes.txt' => 'ignored',
        ]);

        try {
            $this->assertSame(1, $storage->extractImages($archive, $directory));
            $this->assertSame(
                [$directory . '/Чай_m.jpg'],
                $storage->imageFiles($directory)
            );
            $this->assertSame(
                'image-content',
                Storage::disk('public')->get($directory . '/Чай_m.jpg')
            );
            $this->assertSame(
                '/storage/' . $directory . '/%D0%A7%D0%B0%D0%B9_m.jpg',
                $storage->previewUrl(
                    $directory,
                    $directory . '/Чай_m.jpg'
                )
            );
        } finally {
            File::delete($archive);
        }
    }

    public function test_it_rejects_parent_directory_entries(): void
    {
        $storage = app(ProductImageImportStorage::class);
        $directory = $storage->newDirectory();
        $archive = $this->archiveWith([
            '../escape_m.jpg' => 'unsafe',
        ]);

        try {
            $this->expectException(DomainException::class);
            $storage->extractImages($archive, $directory);
        } finally {
            File::delete($archive);
        }
    }

    public function test_it_rejects_a_file_from_another_import_directory(): void
    {
        $storage = app(ProductImageImportStorage::class);
        $directory = $storage->newDirectory();
        $otherDirectory = $storage->newDirectory();

        $this->expectException(DomainException::class);
        $storage->fileExists($directory, $otherDirectory . '/Чай_m.jpg');
    }

    /** @param array<string, string> $files */
    private function archiveWith(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'teaseat-images-');
        if ($path === false) {
            throw new \RuntimeException('Не удалось создать временный ZIP-файл');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Не удалось создать тестовый ZIP-архив');
        }
        foreach ($files as $name => $contents) {
            if (!$zip->addFromString($name, $contents)) {
                throw new \RuntimeException('Не удалось добавить файл в ZIP-архив');
            }
        }
        $zip->close();

        return $path;
    }
}
