<?php

return [
    'photo_requests_per_hour' => (int) env('PHOTO_REQUESTS_PER_HOUR', 20),
    'photo_requests_per_day' => (int) env('PHOTO_REQUESTS_PER_DAY', 100),
];
