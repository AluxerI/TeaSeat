<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

final class ProductImageImportStorage
{
    private const TEMP_ROOT = 'imports/images/temp';
    private const MAX_ARCHIVE_ENTRIES = 2000;
    private const MAX_IMAGE_FILES = 1000;
    private const MAX_IMAGE_BYTES = 25 * 1024 * 1024;
    private const MAX_TOTAL_BYTES = 250 * 1024 * 1024;

    /** @var array<int, string> */
    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'svg',
        'webp',
    ];

    public function newDirectory(): string
    {
        return self::TEMP_ROOT . '/' . Str::uuid()->toString();
    }

    public function extractImages(string $archivePath, string $directory): int
    {
        $directory = $this->directory($directory);
        Storage::disk('public')->makeDirectory($directory);

        $zip = new ZipArchive();
        $openResult = $zip->open($archivePath);
        if ($openResult !== true) {
            throw new DomainException('Не удалось открыть ZIP-архив');
        }

        if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            $zip->close();
            throw new DomainException('В архиве слишком много файлов');
        }

        $count = 0;
        $declaredTotalBytes = 0;
        $actualTotalBytes = 0;
        $filenames = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    throw new DomainException('Не удалось прочитать структуру ZIP-архива');
                }

                $entryName = $this->safeEntryName((string) $stat['name']);
                if ($entryName === null || str_starts_with($entryName, '__MACOSX/')) {
                    continue;
                }
                $this->assertNotSymlink($zip, $index);

                $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
                if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    continue;
                }

                $filename = basename($entryName);
                $filenameKey = mb_strtolower($filename, 'UTF-8');
                if (isset($filenames[$filenameKey])) {
                    throw new DomainException(
                        "В архиве повторяется имя изображения: {$filename}"
                    );
                }

                $size = max(0, (int) ($stat['size'] ?? 0));
                if ($size > self::MAX_IMAGE_BYTES) {
                    throw new DomainException(
                        "Изображение {$filename} превышает 25 МБ"
                    );
                }
                $declaredTotalBytes += $size;
                if ($declaredTotalBytes > self::MAX_TOTAL_BYTES) {
                    throw new DomainException(
                        'Распакованный архив превышает допустимые 250 МБ'
                    );
                }
                if (++$count > self::MAX_IMAGE_FILES) {
                    throw new DomainException('В архиве больше 1000 изображений');
                }

                $stream = $zip->getStream((string) $stat['name']);
                if ($stream === false) {
                    throw new DomainException(
                        "Не удалось прочитать изображение {$filename}"
                    );
                }

                $this->storeLimitedStream(
                    $stream,
                    $directory . '/' . $filename,
                    $filename,
                    $actualTotalBytes
                );

                $filenames[$filenameKey] = true;
            }
        } finally {
            $zip->close();
        }

        if ($count === 0) {
            throw new DomainException('В архиве не найдено поддерживаемых изображений');
        }

        return $count;
    }

    /** @return array<int, string> */
    public function imageFiles(string $directory): array
    {
        $directory = $this->directory($directory);
        $files = array_filter(
            Storage::disk('public')->files($directory),
            fn (string $file): bool => in_array(
                strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                self::ALLOWED_EXTENSIONS,
                true
            )
        );

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($files);
    }

    public function directoryExists(string $directory): bool
    {
        return Storage::disk('public')->exists($this->directory($directory));
    }

    public function fileExists(string $directory, string $file): bool
    {
        return Storage::disk('public')->exists($this->file($directory, $file));
    }

    public function contents(string $directory, string $file): string
    {
        return Storage::disk('public')->get($this->file($directory, $file));
    }

    public function previewUrl(string $directory, string $file): string
    {
        $segments = explode('/', $this->file($directory, $file));

        return '/storage/' . implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            $segments
        ));
    }

    public function deleteDirectory(string $directory): void
    {
        Storage::disk('public')->deleteDirectory(
            $this->directory($directory)
        );
    }

    private function directory(string $directory): string
    {
        $directory = str_replace('\\', '/', trim($directory));
        if (!preg_match(
            '#^' . preg_quote(self::TEMP_ROOT, '#')
                . '/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}'
                . '-[89ab][0-9a-f]{3}-[0-9a-f]{12}$#',
            $directory
        )) {
            throw new DomainException('Некорректный путь импорта изображений');
        }

        return $directory;
    }

    private function file(string $directory, string $file): string
    {
        $directory = $this->directory($directory);
        $file = str_replace('\\', '/', trim($file));
        if (
            dirname($file) !== $directory
            || basename($file) !== substr($file, strlen($directory) + 1)
        ) {
            throw new DomainException('Некорректный путь изображения');
        }

        return $file;
    }

    private function safeEntryName(string $entryName): ?string
    {
        $entryName = str_replace('\\', '/', $entryName);
        if (str_contains($entryName, "\0")) {
            throw new DomainException('ZIP-архив содержит некорректный путь');
        }
        if (
            str_starts_with($entryName, '/')
            || preg_match('/^[A-Za-z]:\//', $entryName)
        ) {
            throw new DomainException('ZIP-архив содержит абсолютный путь');
        }

        $isDirectory = str_ends_with($entryName, '/');
        $segments = explode('/', rtrim($entryName, '/'));
        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                throw new DomainException(
                    'ZIP-архив содержит небезопасный путь'
                );
            }
        }

        return $isDirectory ? null : $entryName;
    }

    /** @param resource $source */
    private function storeLimitedStream(
        $source,
        string $destination,
        string $filename,
        int &$actualTotalBytes
    ): void {
        $buffer = fopen('php://temp/maxmemory:5242880', 'w+b');
        if ($buffer === false) {
            fclose($source);
            throw new DomainException('Не удалось подготовить изображение к импорту');
        }

        $imageBytes = 0;

        try {
            while (!feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) {
                    throw new DomainException(
                        "Не удалось прочитать изображение {$filename}"
                    );
                }

                $length = strlen($chunk);
                $imageBytes += $length;
                if ($imageBytes > self::MAX_IMAGE_BYTES) {
                    throw new DomainException(
                        "Изображение {$filename} превышает 25 МБ"
                    );
                }
                if ($actualTotalBytes + $imageBytes > self::MAX_TOTAL_BYTES) {
                    throw new DomainException(
                        'Распакованный архив превышает допустимые 250 МБ'
                    );
                }

                if ($length > 0 && fwrite($buffer, $chunk) !== $length) {
                    throw new DomainException(
                        "Не удалось подготовить изображение {$filename}"
                    );
                }
            }

            rewind($buffer);
            if (!Storage::disk('public')->writeStream($destination, $buffer)) {
                throw new DomainException(
                    "Не удалось сохранить изображение {$filename}"
                );
            }

            $actualTotalBytes += $imageBytes;
        } finally {
            fclose($source);
            fclose($buffer);
        }
    }

    private function assertNotSymlink(ZipArchive $zip, int $index): void
    {
        $operatingSystem = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex(
            $index,
            $operatingSystem,
            $attributes
        )) {
            return;
        }

        $fileType = ($attributes >> 16) & 0170000;
        if ($fileType === 0120000) {
            throw new DomainException('ZIP-архив содержит символическую ссылку');
        }
    }
}
