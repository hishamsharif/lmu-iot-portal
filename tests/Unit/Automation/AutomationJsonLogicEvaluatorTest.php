<?php

declare(strict_types=1);

use App\Domain\Automation\Services\AutomationJsonLogicEvaluator;

it('evaluates strict equality and inequality operators', function (): void {
    $evaluator = app(AutomationJsonLogicEvaluator::class);

    expect($evaluator->evaluate([
        '===' => [
            ['var' => 'trigger.value'],
            42,
        ],
    ], [
        'trigger' => ['value' => 42],
    ]))->toBeTrue();

    expect($evaluator->evaluate([
        '===' => [
            ['var' => 'trigger.value'],
            42,
        ],
    ], [
        'trigger' => ['value' => '42'],
    ]))->toBeFalse();

    expect($evaluator->evaluate([
        '!==' => [
            ['var' => 'trigger.value'],
            42,
        ],
    ], [
        'trigger' => ['value' => '42'],
    ]))->toBeTrue();
});

it('evaluates double-negation truthiness', function (): void {
    $evaluator = app(AutomationJsonLogicEvaluator::class);

    expect($evaluator->evaluate([
        '!!' => [
            ['var' => 'trigger.value'],
        ],
    ], [
        'trigger' => ['value' => '0'],
    ]))->toBeTrue();

    expect($evaluator->evaluate([
        '!!' => [
            ['var' => 'trigger.value'],
        ],
    ], [
        'trigger' => ['value' => ''],
    ]))->toBeFalse();

    expect($evaluator->evaluate([
        '!!' => [
            ['var' => 'query.value'],
        ],
    ], [
        'query' => ['value' => []],
    ]))->toBeFalse();
});

it('evaluates missing and missing_some operators', function (): void {
    $evaluator = app(AutomationJsonLogicEvaluator::class);

    $missing = $evaluator->evaluate([
        'missing' => ['trigger.value', 'query.value', 'payload.device_id'],
    ], [
        'trigger' => ['value' => 10],
        'payload' => ['device_id' => 55],
    ]);

    expect($missing)->toBe(['query.value']);

    $missingSomeEnoughValues = $evaluator->evaluate([
        'missing_some' => [2, ['trigger.value', 'query.value', 'payload.device_id']],
    ], [
        'trigger' => ['value' => 10],
        'query' => ['value' => 20],
    ]);

    expect($missingSomeEnoughValues)->toBe([]);

    $missingSomeNotEnoughValues = $evaluator->evaluate([
        'missing_some' => [2, ['trigger.value', 'query.value', 'payload.device_id']],
    ], [
        'trigger' => ['value' => 10],
    ]);

    expect($missingSomeNotEnoughValues)->toBe(['query.value', 'payload.device_id']);
});

it('evaluates nested and/or/if expressions', function (): void {
    $evaluator = app(AutomationJsonLogicEvaluator::class);

    $expression = [
        'if' => [
            [
                'and' => [
                    [
                        '>' => [
                            ['var' => 'trigger.value'],
                            100,
                        ],
                    ],
                    [
                        'or' => [
                            [
                                '===' => [
                                    ['var' => 'payload.mode'],
                                    'auto',
                                ],
                            ],
                            [
                                '!!' => [
                                    ['var' => 'query.value'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'pass',
            'fail',
        ],
    ];

    expect($evaluator->evaluate($expression, [
        'trigger' => ['value' => 120],
        'payload' => ['mode' => 'manual'],
        'query' => ['value' => 0],
    ]))->toBe('fail');

    expect($evaluator->evaluate($expression, [
        'trigger' => ['value' => 120],
        'payload' => ['mode' => 'manual'],
        'query' => ['value' => 1],
    ]))->toBe('pass');
});
