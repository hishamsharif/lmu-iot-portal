<?php

declare(strict_types=1);

use App\Console\Commands\IoT\ListenForDeviceStates;

it('decodes valid json objects into arrays', function (): void {
    $command = new ListenForDeviceStates;
    $method = new ReflectionMethod($command, 'decodePayload');

    $decoded = $method->invoke($command, '{"voltage":230.4}');

    expect($decoded)->toBeArray()
        ->toMatchArray([
            'voltage' => 230.4,
        ]);
});

it('returns empty array for empty payload strings', function (): void {
    $command = new ListenForDeviceStates;
    $method = new ReflectionMethod($command, 'decodePayload');

    $decoded = $method->invoke($command, '   ');

    expect($decoded)->toBe([]);
});

it('returns null for invalid json payload strings', function (): void {
    $command = new ListenForDeviceStates;
    $method = new ReflectionMethod($command, 'decodePayload');

    $decoded = $method->invoke($command, '{bad-json}');

    expect($decoded)->toBeNull();
});

it('returns null when json is not an object or array', function (): void {
    $command = new ListenForDeviceStates;
    $method = new ReflectionMethod($command, 'decodePayload');

    $decoded = $method->invoke($command, '"just-a-string"');

    expect($decoded)->toBeNull();
});
