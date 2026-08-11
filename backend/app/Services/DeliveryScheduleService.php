<?php

namespace App\Services;

use App\Models\DeliveryMethod;
use App\Models\DeliveryTimeSlot;
use App\Models\Order;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

class DeliveryScheduleService
{
    /**
     * @return array<int, array{
     *     date: string,
     *     weekday: int,
     *     slots: array<int, array<string, int|string>>
     * }>
     */
    public function availableDates(DeliveryMethod $method): array
    {
        if (!$method->requiresScheduling()) {
            return [];
        }

        $now = $this->now();
        [$firstDate, $lastDate] = $this->dateRange($method, $now);
        if ($firstDate->greaterThan($lastDate)) {
            return [];
        }

        $slots = DeliveryTimeSlot::query()
            ->active()
            ->where('delivery_method_id', $method->id)
            ->orderBy('weekday')
            ->orderBy('time_from')
            ->get();
        if ($slots->isEmpty()) {
            return [];
        }

        $booked = Order::query()
            ->selectRaw(
                'delivery_time_slot_id, scheduled_delivery_date, COUNT(*) AS aggregate'
            )
            ->whereNull('parent_order_id')
            ->whereIn('delivery_time_slot_id', $slots->pluck('id'))
            ->whereBetween('scheduled_delivery_date', [
                $firstDate->toDateString(),
                $lastDate->toDateString(),
            ])
            ->whereNotIn('status', [
                Order::STATUS_CART,
                Order::STATUS_CANCELLED,
            ])
            ->groupBy('delivery_time_slot_id', 'scheduled_delivery_date')
            ->get()
            ->mapWithKeys(function (Order $order): array {
                $date = $order->scheduled_delivery_date?->toDateString();

                return [
                    "{$order->delivery_time_slot_id}|{$date}" =>
                        (int) $order->aggregate,
                ];
            });

        $dates = [];
        for ($date = $firstDate; $date->lte($lastDate); $date = $date->addDay()) {
            $daySlots = [];
            foreach ($slots->where('weekday', $date->isoWeekday()) as $slot) {
                if ($this->slotStart($date, $slot)->lte($now)) {
                    continue;
                }

                $bookedCount = (int) $booked->get(
                    "{$slot->id}|{$date->toDateString()}",
                    0
                );
                $remaining = max(0, (int) $slot->capacity - $bookedCount);
                if ($remaining === 0) {
                    continue;
                }

                $daySlots[] = [
                    'id' => (int) $slot->id,
                    'time_from' => $slot->timeFrom(),
                    'time_to' => $slot->timeTo(),
                    'capacity' => (int) $slot->capacity,
                    'booked' => $bookedCount,
                    'remaining_capacity' => $remaining,
                ];
            }

            if ($daySlots !== []) {
                $dates[] = [
                    'date' => $date->toDateString(),
                    'weekday' => $date->isoWeekday(),
                    'slots' => $daySlots,
                ];
            }
        }

        return $dates;
    }

    /**
     * @return array{
     *     delivery_time_slot_id: int|null,
     *     scheduled_delivery_date: string|null,
     *     delivery_time_from: string|null,
     *     delivery_time_to: string|null
     * }
     */
    public function reserveSelection(
        DeliveryMethod $method,
        ?string $dateValue,
        ?int $slotId,
        ?int $excludeOrderId = null
    ): array {
        if (!$method->requiresScheduling()) {
            if ($dateValue !== null || $slotId !== null) {
                throw new DomainException(
                    'Выбранный способ доставки не поддерживает интервалы'
                );
            }

            return $this->emptySelection();
        }

        if ($dateValue === null || $slotId === null) {
            throw new DomainException('Выберите доступную дату и интервал доставки');
        }

        $date = $this->parseDate($dateValue);
        $now = $this->now();
        [$firstDate, $lastDate] = $this->dateRange($method, $now);
        if ($date->lt($firstDate) || $date->gt($lastDate)) {
            throw new DomainException('Дата находится вне доступного периода доставки');
        }

        /** @var DeliveryTimeSlot|null $slot */
        $slot = DeliveryTimeSlot::query()
            ->active()
            ->where('delivery_method_id', $method->id)
            ->whereKey($slotId)
            ->lockForUpdate()
            ->first();
        if (!$slot || $slot->weekday !== $date->isoWeekday()) {
            throw new DomainException('Интервал недоступен в выбранный день');
        }
        if ($this->slotStart($date, $slot)->lte($now)) {
            throw new DomainException('Интервал доставки уже начался');
        }

        $bookedQuery = Order::query()
            ->whereNull('parent_order_id')
            ->where('delivery_time_slot_id', $slot->id)
            ->whereDate('scheduled_delivery_date', $date->toDateString())
            ->whereNotIn('status', [
                Order::STATUS_CART,
                Order::STATUS_CANCELLED,
            ]);
        if ($excludeOrderId !== null) {
            $bookedQuery->where('id', '!=', $excludeOrderId);
        }

        if ($bookedQuery->count() >= (int) $slot->capacity) {
            throw new DomainException('В выбранном интервале больше нет свободных мест');
        }

        return [
            'delivery_time_slot_id' => (int) $slot->id,
            'scheduled_delivery_date' => $date->toDateString(),
            'delivery_time_from' => $slot->timeFrom(),
            'delivery_time_to' => $slot->timeTo(),
        ];
    }

    public function applyCourierClaimWindow(Builder $query): Builder
    {
        $claimThreshold = $this->now()->addHours($this->claimLeadHours());

        return $query->where(function (Builder $window) use ($claimThreshold): void {
            $window
                ->whereNotNull('orders.parent_order_id')
                ->orWhereNull('orders.scheduled_delivery_date')
                ->orWhereRaw(
                    '(orders.scheduled_delivery_date + orders.delivery_time_from) <= ?',
                    [$claimThreshold->format('Y-m-d H:i:s')]
                );
        });
    }

    public function courierClaimWindowIsOpen(Order $order): bool
    {
        if ($order->parent_order_id !== null
            || $order->scheduled_delivery_date === null
            || $order->delivery_time_from === null) {
            return true;
        }

        return $this->scheduledStart($order)
            ->lte($this->now()->addHours($this->claimLeadHours()));
    }

    public function courierClaimOpensAt(Order $order): ?CarbonImmutable
    {
        if ($order->parent_order_id !== null
            || $order->scheduled_delivery_date === null
            || $order->delivery_time_from === null) {
            return null;
        }

        return $this->scheduledStart($order)
            ->subHours($this->claimLeadHours());
    }

    public function courierClaimLeadHours(): int
    {
        return $this->claimLeadHours();
    }

    public function scheduledStart(Order $order): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $order->scheduled_delivery_date->toDateString()
                . ' ' . $order->delivery_time_from,
            config('app.timezone')
        );
    }

    private function slotStart(
        CarbonImmutable $date,
        DeliveryTimeSlot $slot
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            $date->toDateString() . ' ' . $slot->time_from,
            config('app.timezone')
        );
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function dateRange(
        DeliveryMethod $method,
        CarbonImmutable $now
    ): array {
        $today = $now->startOfDay();
        $minimumDays = max(0, (int) ($method->estimated_days_min ?? 0));
        $horizon = max(1, (int) config('delivery.booking_horizon_days', 30));

        return [
            $today->addDays($minimumDays),
            $today->addDays($horizon - 1),
        ];
    }

    private function parseDate(string $value): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat(
                'Y-m-d',
                $value,
                config('app.timezone')
            )->startOfDay();
        } catch (\Throwable) {
            throw new DomainException('Некорректная дата доставки');
        }

        if ($date->toDateString() !== $value) {
            throw new DomainException('Некорректная дата доставки');
        }

        return $date;
    }

    /** @return array<string, null> */
    private function emptySelection(): array
    {
        return [
            'delivery_time_slot_id' => null,
            'scheduled_delivery_date' => null,
            'delivery_time_from' => null,
            'delivery_time_to' => null,
        ];
    }

    private function claimLeadHours(): int
    {
        return max(0, (int) config('delivery.courier_claim_lead_hours', 24));
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }
}
