<?php

return [
    // Количество календарных дней, доступных покупателю начиная с сегодня.
    'booking_horizon_days' => (int) env('DELIVERY_BOOKING_HORIZON_DAYS', 30),

    // За сколько часов до начала интервала готовый заказ появляется в PWA.
    'courier_claim_lead_hours' => (int) env('COURIER_CLAIM_LEAD_HOURS', 24),
];
