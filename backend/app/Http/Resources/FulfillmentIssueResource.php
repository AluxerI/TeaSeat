<?php

namespace App\Http\Resources;

use App\Models\FulfillmentIssue;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfillmentIssueResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'source_order_id' => $this->source_order_id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'reason' => $this->reason,
            'reason_message' => $this->reasonMessage(),
            'shortage_quantity' => (int) $this->shortage_quantity,
            'reserved_online_before' => (int) $this->reserved_online_before,
            'reserved_seller_before' => (int) $this->reserved_seller_before,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function reasonMessage(): string
    {
        return match ($this->reason) {
            FulfillmentIssue::REASON_ONLINE_RESERVATION_CONFLICT => sprintf(
                'После физической продажи интернет-резервам не хватает %d единиц товара.',
                (int) $this->shortage_quantity
            ),
            FulfillmentIssue::REASON_PHYSICAL_STOCK_DISCREPANCY => sprintf(
                'Физический остаток отличается от учётного на %d единиц товара.',
                (int) $this->shortage_quantity
            ),
            default => 'Требуется проверка менеджера.',
        };
    }
}
