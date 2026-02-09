<?php

declare(strict_types=1);

use App\Domain\DataIngestion\Services\TelemetryDerivationService;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

it('derives values from dependencies and merges them into the final payload', function (): void {
    $service = new TelemetryDerivationService;

    $definitions = new Collection([
        new DerivedParameterDefinition([
            'key' => 'temp_f',
            'data_type' => ParameterDataType::Decimal,
            'expression' => [
                '+' => [
                    [
                        '*' => [
                            ['var' => 'temp_c'],
                            1.8,
                        ],
                    ],
                    32,
                ],
            ],
            'dependencies' => ['temp_c'],
        ]),
    ]);

    $result = $service->derive(
        mutatedValues: ['temp_c' => 10.0],
        derivedParameters: $definitions,
    );

    expect($result['derived_values'])->toMatchArray([
        'temp_f' => 50.0,
    ])->and($result['final_values'])->toMatchArray([
        'temp_c' => 10.0,
        'temp_f' => 50.0,
    ]);
});

it('skips unresolved derived definitions when dependencies are unavailable', function (): void {
    $service = new TelemetryDerivationService;

    $definitions = new Collection([
        new DerivedParameterDefinition([
            'key' => 'heat_index',
            'data_type' => ParameterDataType::Decimal,
            'expression' => [
                '+' => [
                    ['var' => 'temp_c'],
                    ['var' => 'humidity'],
                ],
            ],
            'dependencies' => ['temp_c', 'humidity'],
        ]),
    ]);

    $result = $service->derive(
        mutatedValues: ['temp_c' => 25.0],
        derivedParameters: $definitions,
    );

    expect($result['derived_values'])->toBe([])
        ->and($result['final_values'])->toBe([
            'temp_c' => 25.0,
        ]);
});
