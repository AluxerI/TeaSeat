<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProductImportStorage
{
    public function newCleanFile(string $originalName): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace(
            '/[^\pL\pN._-]+/u',
            '_',
            trim($baseName)
        ) ?: 'import';
        $baseName = trim($baseName, '._-') ?: 'import';

        return sprintf(
            'clean/%s_clean_%s.xlsx',
            $baseName,
            Str::uuid()->toString()
        );
    }

    public function ensureParentDirectory(string $relativePath): void
    {
        File::ensureDirectoryExists(
            dirname($this->path($relativePath)),
            0755,
            true
        );
    }

    public function path(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));

        if (
            $relativePath === ''
            || str_starts_with($relativePath, '/')
            || preg_match('/^[A-Za-z]:\//', $relativePath)
            || str_contains($relativePath, "\0")
        ) {
            throw new InvalidArgumentException('Некорректный путь файла импорта');
        }

        $segments = explode('/', $relativePath);
        if (collect($segments)->contains(
            fn (string $segment): bool => in_array($segment, ['', '.', '..'], true)
        )) {
            throw new InvalidArgumentException('Некорректный путь файла импорта');
        }

        return storage_path(
            'app/imports/' . implode(DIRECTORY_SEPARATOR, $segments)
        );
    }

    public function exists(string $relativePath): bool
    {
        return File::isFile($this->path($relativePath));
    }

    public function delete(string $relativePath): void
    {
        $path = $this->path($relativePath);

        if (File::isFile($path)) {
            File::delete($path);
        }
    }
}
