<?php

declare(strict_types=1);

return [
    'storage_disk' => (string) env('REPORTING_STORAGE_DISK', (string) env('FILESYSTEM_DISK', 'local')),

    'storage_directory' => (string) env('REPORTING_STORAGE_DIRECTORY', 'reports'),

    'queue_connection' => (string) env('REPORTING_QUEUE_CONNECTION', (string) env('QUEUE_CONNECTION', 'redis')),

    'queue' => (string) env('REPORTING_QUEUE', 'default'),

    'default_max_range_days' => (int) env('REPORTING_DEFAULT_MAX_RANGE_DAYS', 31),

    'api' => [
        'base_url' => (string) env('REPORTING_API_BASE_URL', (string) env('APP_URL', 'http://localhost')),
        'timeout_seconds' => (int) env('REPORTING_API_TIMEOUT_SECONDS', 30),
        'token' => (string) env('REPORTING_INTERNAL_TOKEN', (string) env('APP_KEY', 'reporting-local-token')),
        'token_header' => (string) env('REPORTING_INTERNAL_TOKEN_HEADER', 'X-Reporting-Token'),
    ],
];
