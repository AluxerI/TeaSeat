<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ]),
            'author' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'verified_purchase' => $this->order_product_id !== null,
            'company_reply' => $this->whenLoaded('reply', fn () => $this->reply ? [
                'author_name' => $this->reply->author_name,
                'body' => $this->reply->body,
                'created_at' => $this->reply->created_at?->toIso8601String(),
                'edited_at' => $this->reply->edited_at?->toIso8601String(),
            ] : null),
            'customer_edited_at' => $this->customer_edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
