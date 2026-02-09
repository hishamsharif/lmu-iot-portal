<?php

declare(strict_types=1);

return [
    'device_control' => [
        'inject_meta_command_id' => (bool) env('IOT_INJECT_META_COMMAND_ID', true),
        'command_timeout_seconds' => (int) env('IOT_COMMAND_TIMEOUT_SECONDS', 120),
    ],
];
