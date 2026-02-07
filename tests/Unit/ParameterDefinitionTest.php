<?php

declare(strict_types=1);

use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use Tests\TestCase;

uses(TestCase::class);

it('extracts values from payloads using json path', function (): void {
    $definition = new ParameterDefinition([
        'json_path' => '$.status.temp',
        'type' => ParameterDataType::Decimal,
    ]);

    $payload = [
        'status' => [
            'temp' => 22.5,
        ],
    ];

    expect($definition->extractValue($payload))->toBe(22.5);
});

it('applies JsonLogic mutation expressions to values', function (): void {
    $definition = new ParameterDefinition([
        'json_path' => 'temp_c',
        'type' => ParameterDataType::Decimal,
        'mutation_expression' => [
            '+' => [
                ['*' => [
                    ['var' => 'val'],
                    1.8,
                ]],
                32,
            ],
        ],
    ]);

    $payload = ['temp_c' => 10];
    $value = $definition->extractValue($payload);

    expect($definition->mutateValue($value))->toBe(50.0);
});

it('validates required and critical flags', function (): void {
    $definition = new ParameterDefinition([
        'required' => true,
        'is_critical' => true,
        'type' => ParameterDataType::Integer,
        'validation_error_code' => 'REQUIRED',
    ]);

    $result = $definition->validateValue(null);

    expect($result['is_valid'])->toBeFalse()
        ->and($result['is_critical'])->toBeTrue()
        ->and($result['error_code'])->toBe('REQUIRED');
});
