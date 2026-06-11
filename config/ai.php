<?php

return [
    'driver' => env('AI_DRIVER', 'rules'),
    'cache_ttl_days' => (int) env('AI_CACHE_TTL_DAYS', 30),
    'rate_limit_per_hour' => (int) env('AI_RATE_LIMIT_PER_HOUR', 30),
];
