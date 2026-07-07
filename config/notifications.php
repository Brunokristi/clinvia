<?php

return [
    'branch_request_throttle_minutes' => (int) env('BRANCH_REQUEST_THROTTLE_MINUTES', 15),
    'pending_request_digest_interval_hours' => (int) env('PENDING_REQUEST_DIGEST_INTERVAL_HOURS', 12),
    'reminder_hour' => (string) env('BOOKING_REMINDER_DAILY_AT', '18:00'),
    'pending_digest_hour' => (string) env('PENDING_REQUEST_DIGEST_DAILY_AT', '18:15'),
];
