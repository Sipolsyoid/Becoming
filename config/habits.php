<?php

return [
    'max_active' => (int) env('MAX_ACTIVE_HABITS', 30),
    'max_total' => (int) env('MAX_TOTAL_HABITS', 200),
    'photo_requests_per_hour' => (int) env('PHOTO_REQUESTS_PER_HOUR', 20),
    'photo_requests_per_day' => (int) env('PHOTO_REQUESTS_PER_DAY', 100),
];
