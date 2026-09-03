<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductRatingService
{
    /**
     * Добавляет публичные агрегаты рейтинга одним набором подзапросов.
     * Тексты отзывов при этом не загружаются.
     */
    public function withPublicAggregates(Builder $query): Builder
    {
        return $query
            ->withAvg([
                'reviews as rating_average' => fn (Builder $reviewQuery) =>
                    $reviewQuery->published(),
            ], 'rating')
            ->withCount([
                'reviews as reviews_count' => fn (Builder $reviewQuery) =>
                    $reviewQuery->published(),
            ]);
    }

    /** @return array{average:float|null,count:int,distribution:array<int,int>} */
    public function summary(Product $product): array
    {
        $ratings = $product->reviews()
            ->published()
            ->selectRaw('rating, COUNT(*) AS aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $distribution = [];
        $total = 0;
        $sum = 0;
        for ($rating = 1; $rating <= 5; $rating++) {
            $count = (int) ($ratings[$rating] ?? 0);
            $distribution[$rating] = $count;
            $total += $count;
            $sum += $rating * $count;
        }

        return [
            'average' => $total > 0 ? round($sum / $total, 2) : null,
            'count' => $total,
            'distribution' => $distribution,
        ];
    }
}
