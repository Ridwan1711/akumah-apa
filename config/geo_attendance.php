<?php

return [
    'enabled' => env('GEO_ATTENDANCE_ENABLED', true),

    'geofence' => [
        'latitude' => (float) env('GEO_ATTENDANCE_LATITUDE', -7.357080),
        'longitude' => (float) env('GEO_ATTENDANCE_LONGITUDE', 108.125729),
        'radius_meters' => (int) env('GEO_ATTENDANCE_RADIUS_METERS', 500),
    ],

    'time_window' => [
        'grace_before_minutes' => (int) env('GEO_ATTENDANCE_GRACE_BEFORE_MINUTES', 15),
        'grace_after_minutes' => (int) env('GEO_ATTENDANCE_GRACE_AFTER_MINUTES', 20),
    ],

    'location_log' => [
        'retention_days' => (int) env('GEO_ATTENDANCE_RETENTION_DAYS', 60),
        'ping_min_interval_seconds' => (int) env('GEO_ATTENDANCE_PING_MIN_INTERVAL_SECONDS', 300),
    ],
];
