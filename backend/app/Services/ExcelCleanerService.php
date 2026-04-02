<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelCleanerService
{
    /**
     * Очистить Excel файл от битых UTF-8 символов
     */
    public function clean(string $inputPath, string $outputPath = null): array
    {
        $errors = [];
        $cleanedRows = 0;
        $skippedRows = 0;
        
        try {
            $spreadsheet = IOFactory::load($inputPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                return ['errors' => ['Файл пуст'], 'cleaned_rows' => 0, 'skipped_rows' => 0];
            }
            
            $headers = array_shift($rows);
            $cleanedData = [];
            
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                $originalRow = $row;
                $cleanedRow = [];
                $rowHasErrors = false;
                $rowErrors = [];
                
                foreach ($row as $colIndex => $cell) {
                    if (is_string($cell)) {
                        // Проверяем на битый UTF-8
                        if (!mb_check_encoding($cell, 'UTF-8')) {
                            $rowHasErrors = true;
                            $rowErrors[] = "Колонка " . ($colIndex + 1) . ": недопустимые символы";
                            // Пытаемся восстановить
                            $cell = mb_convert_encoding($cell, 'UTF-8', 'auto');
                            if (!mb_check_encoding($cell, 'UTF-8')) {
                                $cell = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '?', $cell);
                            }
                        }
                        
                        // Удаляем управляющие символы
                        $cell = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cell);
                        $cell = trim($cell);
                    }
                    $cleanedRow[] = $cell;
                }
                
                if ($rowHasErrors) {
                    $skippedRows++;
                    $errors[] = "Строка {$rowNum}: " . implode(', ', $rowErrors);
                    continue;
                }
                
                // Проверяем обязательные поля
                $name = trim($cleanedRow[0] ?? '');
                $price = $cleanedRow[1] ?? null;
                
                if (empty($name) && empty($price)) {
                    $skippedRows++;
                    $errors[] = "Строка {$rowNum}: пустая строка, пропущена";
                    continue;
                }
                
                if (empty($name)) {
                    $skippedRows++;
                    $errors[] = "Строка {$rowNum}: отсутствует название товара";
                    continue;
                }
                
                if (empty($price) || !is_numeric($price) || $price <= 0) {
                    $skippedRows++;
                    $errors[] = "Строка {$rowNum}: некорректная цена '{$price}'";
                    continue;
                }
                
                $cleanedData[] = $cleanedRow;
                $cleanedRows++;
            }
            
            if ($outputPath && $cleanedRows > 0) {
                // Создаем новый очищенный Excel
                $newSpreadsheet = new Spreadsheet();
                $newSheet = $newSpreadsheet->getActiveSheet();
                
                // Добавляем заголовки
                foreach ($headers as $colIndex => $header) {
                    $columnLetter = chr(65 + $colIndex);
                    $newSheet->setCellValue($columnLetter . '1', $header);
                }
                
                // Добавляем очищенные данные
                foreach ($cleanedData as $rowIndex => $row) {
                    $excelRow = $rowIndex + 2;
                    foreach ($row as $colIndex => $cell) {
                        $columnLetter = chr(65 + $colIndex);
                        $newSheet->setCellValue($columnLetter . $excelRow, $cell);
                    }
                }
                
                $writer = new Xlsx($newSpreadsheet);
                $writer->save($outputPath);
            }
            
        } catch (\Exception $e) {
            $errors[] = 'Ошибка при очистке файла: ' . $e->getMessage();
        }
        
        return [
            'errors' => $errors,
            'cleaned_rows' => $cleanedRows,
            'skipped_rows' => $skippedRows,
            'total_rows' => $cleanedRows + $skippedRows
        ];
    }
    
    /**
     * Проверить файл без сохранения
     */
    public function validate(string $filePath): array
    {
        return $this->clean($filePath, null);
    }
}