<?php

namespace App\Http\Controllers\Gift;

use App\Http\Controllers\Controller;
use App\Http\Resources\GiftResource;
use App\Http\Resources\GiftSizeProfileResource;
use App\Http\Resources\ProductSizeResource;
use App\Models\ProductSize;
use App\Services\GiftConstructorService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftConstructorController extends Controller
{
    public function __construct(
        protected GiftConstructorService $constructorService
    ) {
    }

    public function advancedOptions(): JsonResponse
    {
        return $this->optionsResponse($this->constructorService->options());
    }

    public function simpleOptions(): JsonResponse
    {
        $options = $this->constructorService->options(simpleOnly: true);
        return response()->json(['data' => [
            'cell_size_mm' => $options['cell_size_mm'],
            'boxes' => GiftSizeProfileResource::collection($options['boxes']),
            'tea_product_sizes' => ProductSizeResource::collection(
                $options['product_sizes']->where('constructor_role', ProductSize::ROLE_TEA)->values()
            ),
            'sweet_product_sizes' => ProductSizeResource::collection(
                $options['product_sizes']->where('constructor_role', ProductSize::ROLE_SWEET)->values()
            ),
            'selection_rules' => [
                'counts_source' => 'box_profile.simple_requirements',
                'allow_duplicate_products' => true,
            ],
        ]]);
    }

    public function validateLayout(Request $request): JsonResponse
    {
        try {
            $prepared = $this->constructorService->prepareAdvanced(
                $this->advancedPayload($request)
            );
            return response()->json(['data' => [
                'valid' => true,
                'box' => new GiftSizeProfileResource($prepared['box']),
                'layout' => $prepared['layout'],
            ]]);
        } catch (DomainException $exception) {
            return $this->invalid($exception);
        }
    }

    public function advancedQuote(Request $request): JsonResponse
    {
        $payload = $this->advancedPayload($request, quote: true);
        try {
            return response()->json(['data' => $this->constructorService->quote(
                $request->user(),
                $this->constructorService->prepareAdvanced($payload),
                $payload
            )]);
        } catch (DomainException $exception) {
            return $this->invalid($exception);
        }
    }

    public function simpleQuote(Request $request): JsonResponse
    {
        $payload = $this->simplePayload($request, quote: true);
        try {
            return response()->json(['data' => $this->constructorService->quote(
                $request->user(),
                $this->constructorService->prepareSimple($payload),
                $payload
            )]);
        } catch (DomainException $exception) {
            return $this->invalid($exception);
        }
    }

    public function advancedStore(Request $request): JsonResponse
    {
        $payload = $this->advancedPayload($request, requireName: true);
        try {
            $gift = $this->constructorService->create(
                $request->user(),
                $this->constructorService->prepareAdvanced($payload),
                $payload
            );
            return response()->json(['data' => new GiftResource($gift)], 201);
        } catch (DomainException $exception) {
            return $this->invalid($exception);
        }
    }

    public function simpleStore(Request $request): JsonResponse
    {
        $payload = $this->simplePayload($request, requireName: true);
        try {
            $gift = $this->constructorService->create(
                $request->user(),
                $this->constructorService->prepareSimple($payload),
                $payload
            );
            return response()->json(['data' => new GiftResource($gift)], 201);
        } catch (DomainException $exception) {
            return $this->invalid($exception);
        }
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => GiftResource::collection(
                $this->constructorService->owned($request->user())
            ),
        ]);
    }

    public function show(Request $request, int $gift): JsonResponse
    {
        return response()->json(['data' => new GiftResource(
            $this->constructorService->findOwned($request->user(), $gift)
        )]);
    }

    public function update(Request $request, int $gift): JsonResponse
    {
        $payload = $this->advancedPayload($request, requireName: true);
        try {
            $updated = $this->constructorService->update(
                $request->user(),
                $gift,
                $this->constructorService->prepareAdvanced($payload),
                $payload
            );
            return response()->json(['data' => new GiftResource($updated)]);
        } catch (DomainException $exception) {
            return $this->invalid($exception);
        }
    }

    public function destroy(Request $request, int $gift): JsonResponse
    {
        $this->constructorService->findOwned($request->user(), $gift);
        $this->constructorService->archive($request->user(), $gift);
        return response()->json(null, 204);
    }

    private function advancedPayload(
        Request $request,
        bool $requireName = false,
        bool $quote = false
    ): array {
        return $request->validate([
            'box_profile_id' => ['required', 'integer', 'exists:gift_size_profiles,id'],
            'name' => [$requireName ? 'required' : 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:255'],
            'quantity' => [$quote ? 'nullable' : 'prohibited', 'integer', 'min:1', 'max:100'],
            'discount_selection' => [$quote ? 'nullable' : 'prohibited', 'array'],
            'discount_selection.type' => ['required_with:discount_selection', 'in:personal,coupon'],
            'discount_selection.discount_id' => ['required_if:discount_selection.type,personal', 'integer', 'exists:discounts,id'],
            'discount_selection.code' => ['required_if:discount_selection.type,coupon', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:' . config('gifts.max_layout_items', 40)],
            'items.*.client_item_id' => ['required', 'uuid', 'distinct'],
            'items.*.product_size_id' => ['required', 'integer', 'exists:product_sizes,id'],
            'items.*.position_x' => ['required', 'integer', 'min:0'],
            'items.*.position_y' => ['required', 'integer', 'min:0'],
            'items.*.is_rotated' => ['required', 'boolean'],
        ]);
    }

    private function simplePayload(
        Request $request,
        bool $requireName = false,
        bool $quote = false
    ): array {
        return $request->validate([
            'box_profile_id' => ['required', 'integer', 'exists:gift_size_profiles,id'],
            'name' => [$requireName ? 'required' : 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:255'],
            'quantity' => [$quote ? 'nullable' : 'prohibited', 'integer', 'min:1', 'max:100'],
            'discount_selection' => [$quote ? 'nullable' : 'prohibited', 'array'],
            'discount_selection.type' => ['required_with:discount_selection', 'in:personal,coupon'],
            'discount_selection.discount_id' => ['required_if:discount_selection.type,personal', 'integer', 'exists:discounts,id'],
            'discount_selection.code' => ['required_if:discount_selection.type,coupon', 'string', 'max:100'],
            'tea_product_size_ids' => [
                'required',
                'array',
                'min:1',
                'max:' . config('gifts.max_layout_items', 40),
            ],
            'tea_product_size_ids.*' => [
                'required',
                'integer',
                'exists:product_sizes,id',
            ],
            'sweet_product_size_ids' => [
                'required',
                'array',
                'min:1',
                'max:' . config('gifts.max_layout_items', 40),
            ],
            'sweet_product_size_ids.*' => [
                'required',
                'integer',
                'exists:product_sizes,id',
            ],
        ]);
    }

    private function optionsResponse(array $options): JsonResponse
    {
        return response()->json(['data' => [
            'cell_size_mm' => $options['cell_size_mm'],
            'boxes' => GiftSizeProfileResource::collection($options['boxes']),
            'product_sizes' => ProductSizeResource::collection($options['product_sizes']),
        ]]);
    }

    private function invalid(DomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'gift_configuration_invalid',
        ], 422);
    }
}
