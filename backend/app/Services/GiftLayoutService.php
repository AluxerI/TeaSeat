<?php

namespace App\Services;

use App\Models\GiftSizeProfile;
use App\Models\ProductSize;
use DomainException;
use Illuminate\Support\Collection;

class GiftLayoutService
{
    /**
     * @param Collection<int, ProductSize> $sizes
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function validate(
        GiftSizeProfile $box,
        Collection $sizes,
        array $items
    ): array {
        $this->assertBox($box);
        $this->assertItemLimit($items);
        $sizesById = $sizes->keyBy('id');
        $occupied = [];
        $normalized = [];
        $weight = 0;

        foreach (array_values($items) as $index => $item) {
            $size = $sizesById->get((int) ($item['product_size_id'] ?? 0));
            if (!$size) {
                throw new DomainException('Один из форматов товара не найден');
            }
            $size->assertConstructorReady();

            $rotated = (bool) ($item['is_rotated'] ?? false);
            if ($rotated && !$size->sizeProfile->can_rotate) {
                throw new DomainException("Товар «{$size->product->name}» нельзя поворачивать");
            }
            [$width, $height] = $this->dimensions($size, $rotated);
            $x = (int) ($item['position_x'] ?? -1);
            $y = (int) ($item['position_y'] ?? -1);

            if ($x < 0 || $y < 0
                || $x + $width > $box->width_cells
                || $y + $height > $box->height_cells) {
                throw new DomainException("Товар «{$size->product->name}» выходит за границы коробки");
            }

            for ($cellX = $x; $cellX < $x + $width; $cellX++) {
                for ($cellY = $y; $cellY < $y + $height; $cellY++) {
                    $cell = "{$cellX}:{$cellY}";
                    if (isset($occupied[$cell])) {
                        throw new DomainException('Товары в подарке перекрывают друг друга');
                    }
                    $occupied[$cell] = true;
                }
            }

            $weight += $this->weightFor($size);
            $normalized[] = [
                'client_item_id' => (string) $item['client_item_id'],
                'product_size_id' => (int) $size->id,
                'product_id' => (int) $size->product_id,
                'product_name' => $size->product->name,
                'product_quantity' => (int) $size->product_quantity,
                'position_x' => $x,
                'position_y' => $y,
                'is_rotated' => $rotated,
                'width_cells' => $width,
                'height_cells' => $height,
                'sort_order' => $index,
            ];
        }

        if ($box->max_weight_grams !== null && $weight > $box->max_weight_grams) {
            throw new DomainException(sprintf(
                'Вес подарка превышает ограничение коробки на %d г',
                $weight - $box->max_weight_grams
            ));
        }

        return $normalized;
    }

    /**
     * @param Collection<int, ProductSize> $sizes
     * @return array<int, array<string, mixed>>
     */
    public function autoPlace(GiftSizeProfile $box, Collection $sizes): array
    {
        $this->assertBox($box);
        $this->assertItemLimit($sizes->all());
        $pieces = $sizes->values()->map(function (ProductSize $size, int $index): array {
            $size->assertConstructorReady();
            return [
                'client_item_id' => (string) \Illuminate\Support\Str::uuid(),
                'product_size_id' => (int) $size->id,
                'size' => $size,
                'original_order' => $index,
            ];
        })->sortByDesc(fn (array $piece): int =>
            $piece['size']->sizeProfile->width_cells * $piece['size']->sizeProfile->height_cells
        )->values()->all();

        $placed = $this->placeRecursively($box, $pieces, 0, [], []);
        if ($placed === null) {
            throw new DomainException('Выбранные товары невозможно разместить в этой коробке');
        }

        usort($placed, fn (array $left, array $right): int =>
            $left['original_order'] <=> $right['original_order']
        );
        $payload = array_map(function (array $item): array {
            unset($item['original_order']);
            return $item;
        }, $placed);

        return $this->validate($box, $sizes, $payload);
    }

    public function canPlaceSingleItem(
        GiftSizeProfile $box,
        ProductSize $size
    ): bool {
        try {
            $this->assertBox($box);
            $size->assertConstructorReady();
        } catch (DomainException) {
            return false;
        }

        if ($box->max_weight_grams !== null
            && $this->weightFor($size) > $box->max_weight_grams) {
            return false;
        }

        $orientations = [false];
        if ($size->sizeProfile->can_rotate
            && $size->sizeProfile->width_cells !== $size->sizeProfile->height_cells) {
            $orientations[] = true;
        }

        foreach ($orientations as $rotated) {
            [$width, $height] = $this->dimensions($size, $rotated);
            if ($width <= $box->width_cells && $height <= $box->height_cells) {
                return true;
            }
        }

        return false;
    }

    private function placeRecursively(
        GiftSizeProfile $box,
        array $pieces,
        int $index,
        array $occupied,
        array $placed
    ): ?array {
        if ($index >= count($pieces)) {
            return $placed;
        }

        $piece = $pieces[$index];
        /** @var ProductSize $size */
        $size = $piece['size'];
        $rotations = $size->sizeProfile->can_rotate
            && $size->sizeProfile->width_cells !== $size->sizeProfile->height_cells
            ? [false, true]
            : [false];

        foreach ($rotations as $rotated) {
            [$width, $height] = $this->dimensions($size, $rotated);
            for ($y = 0; $y <= $box->height_cells - $height; $y++) {
                for ($x = 0; $x <= $box->width_cells - $width; $x++) {
                    $cells = [];
                    $free = true;
                    for ($cellX = $x; $cellX < $x + $width; $cellX++) {
                        for ($cellY = $y; $cellY < $y + $height; $cellY++) {
                            $cell = "{$cellX}:{$cellY}";
                            if (isset($occupied[$cell])) {
                                $free = false;
                                break 2;
                            }
                            $cells[] = $cell;
                        }
                    }
                    if (!$free) {
                        continue;
                    }

                    $nextOccupied = $occupied;
                    foreach ($cells as $cell) {
                        $nextOccupied[$cell] = true;
                    }
                    $nextPlaced = $placed;
                    $nextPlaced[] = [
                        'client_item_id' => $piece['client_item_id'],
                        'product_size_id' => $piece['product_size_id'],
                        'position_x' => $x,
                        'position_y' => $y,
                        'is_rotated' => $rotated,
                        'original_order' => $piece['original_order'],
                    ];
                    $result = $this->placeRecursively(
                        $box,
                        $pieces,
                        $index + 1,
                        $nextOccupied,
                        $nextPlaced
                    );
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
        }

        return null;
    }

    private function assertBox(GiftSizeProfile $box): void
    {
        if (!$box->is_active || $box->kind !== GiftSizeProfile::KIND_BOX) {
            throw new DomainException('Выбранная коробка недоступна');
        }
    }

    private function assertItemLimit(array $items): void
    {
        if ($items === []) {
            throw new DomainException('Добавьте хотя бы один товар в подарок');
        }
        if (count($items) > config('gifts.max_layout_items', 40)) {
            throw new DomainException('В подарке слишком много отдельных позиций');
        }
    }

    private function dimensions(ProductSize $size, bool $rotated): array
    {
        $width = (int) $size->sizeProfile->width_cells;
        $height = (int) $size->sizeProfile->height_cells;
        return $rotated ? [$height, $width] : [$width, $height];
    }

    private function weightFor(ProductSize $size): int
    {
        if ($size->product->isWeighted()) {
            return (int) $size->product_quantity;
        }
        return max(0, (int) ($size->product->weight_grams ?? 0))
            * (int) $size->product_quantity;
    }
}
