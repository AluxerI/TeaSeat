<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait HasIcon
{
    public function uploadIcon(UploadedFile $file, string $disk = 'public'): string
    {
        $path = $this->getIconDirectory();
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs($path, $filename, $disk);
        $this->icon = $filePath;
        $this->save();
        return $filePath;
    }

    public function getIconDirectory(): string
    {
        $type = $this->getIconFolderType();
        $id = $this->id ?? 'temp';
        return "category-icons/{$type}/{$id}";
    }

    public function moveIconToFolder(?string $tempPath = null): ?string
    {
        $path = $tempPath ?? $this->icon;
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }
        $expectedPath = $this->getIconDirectory() . '/' . basename($path);
        if ($path === $expectedPath) {
            return $path;
        }
        Storage::disk('public')->makeDirectory($this->getIconDirectory());
        Storage::disk('public')->move($path, $expectedPath);
        $this->icon = $expectedPath;
        $this->saveQuietly();
        return $expectedPath;
    }

    public function deleteIcon(): bool
    {
        if ($this->icon && Storage::disk('public')->exists($this->icon)) {
            Storage::disk('public')->delete($this->icon);
            $this->icon = null;
            $this->save();
            return true;
        }
        return false;
    }

    public function deleteIconDirectory(): bool
    {
        $directory = dirname($this->getIconDirectory());
        if (Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->deleteDirectory($directory);
            return true;
        }
        return false;
    }

    public function getIconUrlAttribute(): ?string
    {
        if (!$this->icon) {
            return null;
        }
        return asset('storage/' . $this->icon);
    }

    protected function getIconFolderType(): string
    {
        $className = class_basename($this);
        return match($className) {
            'Category' => 'categories',
            'Subcategory' => 'subcategories',
            'Sub_Subcategory' => 'sub-subcategories',
            default => strtolower($className)
        };
    }

    public function copyIconFromTemp(string $tempPath, string $disk = 'public'): ?string
    {
        if (!Storage::disk('public')->exists($tempPath)) {
            return null;
        }
        return $this->moveIconToFolder($tempPath);
    }
}
