import { JSON_LOGIC_OPERATOR_DEFINITIONS } from '../constants.js';
import { isPlainObject } from './helpers.js';

export const JSON_LOGIC_OPERATOR_MAP = JSON_LOGIC_OPERATOR_DEFINITIONS.reduce((carry, definition) => {
    carry[definition.value] = definition;

    return carry;
}, {});

export const SUPPORTED_JSON_LOGIC_OPERATORS = new Set(Object.keys(JSON_LOGIC_OPERATOR_MAP));

export function deepCloneJsonValue(value) {
    if (typeof structuredClone === 'function') {
        return structuredClone(value);
    }

    return JSON.parse(JSON.stringify(value));
}

export function isJsonLogicExpressionObject(value) {
    return isPlainObject(value) && Object.keys(value).length === 1;
}

export function getExpressionOperator(expression) {
    if (!isJsonLogicExpressionObject(expression)) {
        return null;
    }

    return Object.keys(expression)[0] ?? null;
}

export function isSupportedExpression(expression) {
    const operator = getExpressionOperator(expression);

    return typeof operator === 'string' && SUPPORTED_JSON_LOGIC_OPERATORS.has(operator);
}

export function getOperatorArguments(operator, rawValue) {
    if (operator === 'var') {
        if (Array.isArray(rawValue)) {
            if (rawValue.length === 0) {
                return ['trigger.value'];
            }

            return rawValue;
        }

        return [typeof rawValue === 'string' ? rawValue : 'trigger.value'];
    }

    if (operator === 'missing') {
        if (!Array.isArray(rawValue)) {
            return ['trigger.value'];
        }

        return rawValue.map((entry) => String(entry));
    }

    if (operator === 'missing_some') {
        if (!Array.isArray(rawValue)) {
            return [1, ['trigger.value', 'query.value']];
        }

        const minimumCount = Number.isFinite(Number(rawValue[0])) ? Number(rawValue[0]) : 1;
        const variablePaths = Array.isArray(rawValue[1])
            ? rawValue[1].map((entry) => String(entry))
            : ['trigger.value', 'query.value'];

        return [minimumCount, variablePaths];
    }

    if (Array.isArray(rawValue)) {
        return rawValue;
    }

    return [rawValue];
}

export function encodeOperatorArguments(operator, args) {
    const resolvedArgs = Array.isArray(args) ? args : [];

    if (operator === 'var') {
        const path = typeof resolvedArgs[0] === 'string' && resolvedArgs[0] !== ''
            ? resolvedArgs[0]
            : 'trigger.value';

        if (resolvedArgs.length >= 2) {
            return [path, resolvedArgs[1]];
        }

        return path;
    }

    if (operator === 'missing') {
        const paths = resolvedArgs
            .map((entry) => (typeof entry === 'string' ? entry.trim() : ''))
            .filter((entry) => entry !== '');

        return paths;
    }

    if (operator === 'missing_some') {
        const minimumCount = Number.isFinite(Number(resolvedArgs[0])) ? Number(resolvedArgs[0]) : 1;
        const variablePaths = Array.isArray(resolvedArgs[1])
            ? resolvedArgs[1]
                .map((entry) => (typeof entry === 'string' ? entry.trim() : ''))
                .filter((entry) => entry !== '')
            : [];

        return [minimumCount, variablePaths];
    }

    return resolvedArgs;
}

function defaultLiteralExpressionForOperator(operator) {
    if (['>', '>=', '<', '<=', '==', '===', '!=', '!=='].includes(operator)) {
        return [{ var: 'trigger.value' }, 0];
    }

    if (['+', '-', '*', '/', 'min', 'max'].includes(operator)) {
        return [1, 1];
    }

    if (operator === 'and') {
        return [
            { '>': [{ var: 'trigger.value' }, 0] },
            { '<': [{ var: 'trigger.value' }, 1000] },
        ];
    }

    if (operator === 'or') {
        return [
            { '>': [{ var: 'trigger.value' }, 0] },
            { '<': [{ var: 'query.value' }, 10] },
        ];
    }

    if (operator === '!') {
        return [{ var: 'trigger.value' }];
    }

    if (operator === '!!') {
        return [{ var: 'trigger.value' }];
    }

    if (operator === 'if') {
        return [
            { '>': [{ var: 'trigger.value' }, 0] },
            true,
            false,
        ];
    }

    if (operator === 'var') {
        return 'trigger.value';
    }

    if (operator === 'missing') {
        return ['trigger.value'];
    }

    if (operator === 'missing_some') {
        return [1, ['trigger.value', 'query.value']];
    }

    return [true, true];
}

export function buildDefaultExpression(operator = '>') {
    return {
        [operator]: defaultLiteralExpressionForOperator(operator),
    };
}

export function collectUnsupportedOperators(expression, found = new Set()) {
    if (Array.isArray(expression)) {
        expression.forEach((item) => collectUnsupportedOperators(item, found));

        return found;
    }

    if (!isJsonLogicExpressionObject(expression)) {
        return found;
    }

    const operator = getExpressionOperator(expression);

    if (typeof operator !== 'string') {
        return found;
    }

    if (!SUPPORTED_JSON_LOGIC_OPERATORS.has(operator)) {
        found.add(operator);
    }

    const value = expression[operator];

    if (Array.isArray(value)) {
        value.forEach((item) => collectUnsupportedOperators(item, found));
    } else {
        collectUnsupportedOperators(value, found);
    }

    return found;
}

export function ensureSingleRootJsonLogic(value) {
    return isPlainObject(value) && Object.keys(value).length === 1;
}
