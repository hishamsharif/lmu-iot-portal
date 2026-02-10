<?php

declare(strict_types=1);

return [
    'nats' => [
        'host' => (string) env('IOT_NATS_HOST', (string) env('INGESTION_NATS_HOST', '127.0.0.1')),
        'port' => (int) env('IOT_NATS_PORT', (int) env('INGESTION_NATS_PORT', 4223)),
    ],

    'mqtt' => [
        'host' => (string) env('IOT_MQTT_HOST', (string) env('IOT_NATS_HOST', (string) env('INGESTION_NATS_HOST', '127.0.0.1'))),
        'port' => (int) env('IOT_MQTT_PORT', 1883),
    ],

    'device_control' => [
        'inject_meta_command_id' => (bool) env('IOT_INJECT_META_COMMAND_ID', true),
        'command_timeout_seconds' => (int) env('IOT_COMMAND_TIMEOUT_SECONDS', 120),
    ],
];
