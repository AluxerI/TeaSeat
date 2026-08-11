<?php

namespace App\Http\Resources;

use App\Models\Review;

class ManagerReviewResource extends ReviewResource
{
    public function toArray($request): array
    {
        return parent::toArray($request) + [
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'order_product_id' => $this->order_product_id,
            'source_order' => $this->whenLoaded('orderProduct', fn () =>
                $this->orderProduct?->order ? [
                    'id' => $this->orderProduct->order->id,
                    'status' => $this->orderProduct->order->status,
                ] : null),
            'latest_moderation' => $this->whenLoaded('latestModeration', fn () =>
                $this->moderation($this->latestModeration)),
            'moderation_history' => $this->whenLoaded('moderationLogs', fn () =>
                $this->moderationLogs->map(fn ($log) => $this->moderation($log))->values()),
            'actions' => [
                'can_hide' => $this->status === Review::STATUS_PUBLISHED,
                'can_restore' => $this->status === Review::STATUS_HIDDEN,
                'can_reply' => true,
                'can_edit_customer_text' => false,
            ],
        ];
    }

    private function moderation($log): ?array
    {
        if (!$log) {
            return null;
        }

        return [
            'id' => $log->id,
            'action' => $log->action,
            'reason_code' => $log->reason_code,
            'comment' => $log->comment,
            'moderator' => [
                'id' => $log->moderator_id,
                'name' => $log->moderator_name,
            ],
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
