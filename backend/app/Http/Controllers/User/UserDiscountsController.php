<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\PriceCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\DiscountResource;
use Illuminate\Support\Facades\Log;
use App\Models\User;


class UserDiscountsController extends Controller
{
    protected $priceCalculator;

    public function __construct(PriceCalculatorService $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }

    /**
     * Получить все персональные скидки пользователя
     */
    public function __invoke(Request $request)
    {
        try {
            $user = Auth::user();
            
            $discounts = $this->priceCalculator->getUserDiscounts($user);

            return DiscountResource::collection($discounts);

        } catch (\Exception $e) {
            Log::error('Ошибка при получении скидок пользователя', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при получении скидок',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
