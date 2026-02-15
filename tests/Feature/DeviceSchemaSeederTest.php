<?php

declare(strict_types=1);

use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use Database\Seeders\DeviceSchemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds rgb led controller firmware template from the esp-32-rgb-light file', function (): void {
    $this->seed(DeviceSchemaSeeder::class);

    $version = DeviceSchemaVersion::query()
        ->whereHas('schema.deviceType', fn ($query) => $query->where('key', 'rgb_led_controller'))
        ->where('status', 'active')
        ->orderBy('id')
        ->first();

    expect($version)->not->toBeNull()
        ->and($version?->firmware_template)->toBeString()
        ->and($version?->firmware_template)->toContain('PIN_WIFI_STATUS_LED')
        ->and($version?->firmware_template)->toContain('PIN_MQTT_STATUS_LED')
        ->and($version?->firmware_template)->toContain('const char* MQTT_CLIENT   = "{{MQTT_CLIENT_ID}}"');
});
