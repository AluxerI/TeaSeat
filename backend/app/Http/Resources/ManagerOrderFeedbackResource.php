<?php

namespace App\Http\Resources;

use App\Models\OrderFeedback;

class ManagerOrderFeedbackResource extends OrderFeedbackResource
{
    public function toArray($request): array
    {
        return parent::toArray($request) + [
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'status' => $this->order->status,
                'created_at' => $this->order->created_at?->toIso8601String(),
            ]),
            'latest_moderation' => $this->whenLoaded('latestModeration', fn () =>
                $this->moderation($this->latestModeration)),
            'moderation_history' => $this->whenLoaded('moderationLogs', fn () =>
                $this->moderationLogs->map(fn ($log) => $this->moderation($log))->values()),
            'actions' => [
                'can_hide' => $this->status === OrderFeedback::STATUS_PUBLISHED,
                'can_restore' => $this->status === OrderFeedback::STATUS_HIDDEN,
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
