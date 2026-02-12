<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AUTOMATION_PIPELINE_ENABLED', true),

    'queue_connection' => (string) env('AUTOMATION_PIPELINE_QUEUE_CONNECTION', (string) env('QUEUE_CONNECTION', 'database')),

    'queue' => (string) env('AUTOMATION_PIPELINE_QUEUE', 'default'),

    'log_channel' => (string) env('AUTOMATION_PIPELINE_LOG_CHANNEL', 'automation_pipeline'),
];
