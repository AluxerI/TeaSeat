<?php

return [
    'price_snapshot_ttl_hours' => (int) env('SELLER_PRICE_SNAPSHOT_TTL_HOURS', 48),
    'clock_skew_minutes' => (int) env('SELLER_CLOCK_SKEW_MINUTES', 5),
];
