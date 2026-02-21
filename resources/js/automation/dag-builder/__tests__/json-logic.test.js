import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildDefaultExpression,
    collectUnsupportedOperators,
    encodeOperatorArguments,
    ensureSingleRootJsonLogic,
    getOperatorArguments,
    isSupportedExpression,
} from '../logic/json-logic.js';
import { normalizeConfigForSave } from '../logic/config.js';

const operatorRoundTripSamples = {
    var: 'trigger.value',
    if: [{ '>': [{ var: 'trigger.value' }, 1] }, true, false],
    and: [{ '>': [{ var: 'trigger.value' }, 1] }, { '<': [{ var: 'trigger.value' }, 9] }],
    or: [{ '===': [{ var: 'query.value' }, 1] }, { '!==': [{ var: 'query.value' }, 2] }],
    '!': [{ var: 'trigger.value' }],
    '!!': [{ var: 'trigger.value' }],
    '==': [{ var: 'trigger.value' }, 1],
    '===': [{ var: 'trigger.value' }, 1],
    '!=': [{ var: 'trigger.value' }, 1],
    '!==': [{ var: 'trigger.value' }, 1],
    '>': [{ var: 'trigger.value' }, 1],
    '>=': [{ var: 'trigger.value' }, 1],
    '<': [{ var: 'trigger.value' }, 1],
    '<=': [{ var: 'trigger.value' }, 1],
    '+': [1, 2, 3],
    '-': [7, 2],
    '*': [2, 3],
    '/': [6, 2],
    min: [2, 3, 1],
    max: [2, 3, 1],
    missing: ['trigger.value', 'query.value'],
    missing_some: [1, ['trigger.value', 'query.value']],
};

test('json logic operator argument encode/decode round-trips supported operators', () => {
    Object.entries(operatorRoundTripSamples).forEach(([operator, rawValue]) => {
        const decoded = getOperatorArguments(operator, rawValue);
        const encoded = encodeOperatorArguments(operator, decoded);

        assert.deepEqual(encoded, rawValue, `failed for ${operator}`);
    });
});

test('default expressions produce supported single-root expressions', () => {
    Object.keys(operatorRoundTripSamples).forEach((operator) => {
        const expression = buildDefaultExpression(operator);

        assert.equal(ensureSingleRootJsonLogic(expression), true, operator);
        assert.equal(isSupportedExpression(expression), true, operator);
    });
});

test('detects unsupported operators recursively', () => {
    const expression = {
        and: [
            { '>': [{ var: 'trigger.value' }, 10] },
            { foo: [{ bar: [1, 2] }] },
        ],
    };

    const unsupported = Array.from(collectUnsupportedOperators(expression)).sort();

    assert.deepEqual(unsupported, ['bar', 'foo']);
});

test('normalizes visual-builder condition drafts', () => {
    const normalized = normalizeConfigForSave('condition', {
        mode: 'json_logic',
        json_logic_editor_tab: 'builder',
        json_logic_value: {
            and: [
                { '>': [{ var: 'trigger.value' }, 200] },
                { '!!': [{ var: 'query.value' }] },
            ],
        },
    });

    assert.deepEqual(normalized.json_logic, {
        and: [
            { '>': [{ var: 'trigger.value' }, 200] },
            { '!!': [{ var: 'query.value' }] },
        ],
    });
});

test('validates advanced JSON tab as single-root object before save', () => {
    assert.throws(
        () => normalizeConfigForSave('condition', {
            mode: 'json_logic',
            json_logic_editor_tab: 'advanced',
            json_logic_text: '{"and":[1,2],"or":[3,4]}',
        }),
        /single root operator/,
    );

    const normalized = normalizeConfigForSave('condition', {
        mode: 'json_logic',
        json_logic_editor_tab: 'advanced',
        json_logic_text: '{"if":[{"===":[{"var":"trigger.value"},10]},true,false]}',
    });

    assert.deepEqual(normalized.json_logic, {
        if: [{ '===': [{ var: 'trigger.value' }, 10] }, true, false],
    });
});
