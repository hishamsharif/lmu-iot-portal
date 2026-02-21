import React, { useEffect, useMemo, useState } from 'react';
import {
    JSON_LOGIC_OPERATOR_DEFINITIONS,
} from '../constants';
import {
    buildDefaultExpression,
    deepCloneJsonValue,
    encodeOperatorArguments,
    getExpressionOperator,
    getOperatorArguments,
} from '../logic/json-logic';
import { isPlainObject, safeJsonStringify } from '../logic/helpers';

function inferLiteralType(value) {
    if (value === null) {
        return 'null';
    }

    if (typeof value === 'number') {
        return 'number';
    }

    if (typeof value === 'boolean') {
        return 'boolean';
    }

    if (typeof value === 'string') {
        return 'string';
    }

    return 'json';
}

function buildOperatorDefinitionMap(operatorDefinitions) {
    return operatorDefinitions.reduce((carry, definition) => {
        if (typeof definition?.value !== 'string' || definition.value === '') {
            return carry;
        }

        carry[definition.value] = definition;

        return carry;
    }, {});
}

function normalizeArgumentCount(operator, args, operatorDefinitionMap) {
    const definition = operatorDefinitionMap[operator];

    if (!definition) {
        return args;
    }

    const minArgs = Number.isFinite(Number(definition.minArgs)) ? Number(definition.minArgs) : 1;
    const maxArgs = Number.isFinite(Number(definition.maxArgs)) ? Number(definition.maxArgs) : null;

    const normalizedArgs = Array.isArray(args) ? [...args] : [];

    while (normalizedArgs.length < minArgs) {
        normalizedArgs.push(0);
    }

    if (maxArgs !== null && normalizedArgs.length > maxArgs) {
        return normalizedArgs.slice(0, maxArgs);
    }

    return normalizedArgs;
}

function buildExpressionWithArgs(operator, args) {
    return {
        [operator]: encodeOperatorArguments(operator, args),
    };
}

function readOnlyUnsupportedMessage(operator) {
    return `Unsupported operator \"${operator}\" in visual builder. Use Advanced JSON to edit this section.`;
}

function isExpressionSupportedByDefinitions(expression, operatorDefinitionMap) {
    if (!isPlainObject(expression)) {
        return false;
    }

    const operator = getExpressionOperator(expression);

    return typeof operator === 'string' && Object.prototype.hasOwnProperty.call(operatorDefinitionMap, operator);
}

function collectUnsupportedOperatorsByDefinitions(expression, operatorDefinitionMap, found = new Set()) {
    if (Array.isArray(expression)) {
        expression.forEach((item) => collectUnsupportedOperatorsByDefinitions(item, operatorDefinitionMap, found));

        return found;
    }

    if (!isPlainObject(expression) || Object.keys(expression).length !== 1) {
        return found;
    }

    const operator = getExpressionOperator(expression);

    if (typeof operator !== 'string') {
        return found;
    }

    if (!Object.prototype.hasOwnProperty.call(operatorDefinitionMap, operator)) {
        found.add(operator);
    }

    const value = expression[operator];

    if (Array.isArray(value)) {
        value.forEach((item) => collectUnsupportedOperatorsByDefinitions(item, operatorDefinitionMap, found));
    } else {
        collectUnsupportedOperatorsByDefinitions(value, operatorDefinitionMap, found);
    }

    return found;
}

function JsonLiteralEditor({ value, onChange, readOnly }) {
    const literalType = inferLiteralType(value);
    const [jsonDraft, setJsonDraft] = useState(() => safeJsonStringify(value));
    const [jsonError, setJsonError] = useState('');

    useEffect(() => {
        if (literalType !== 'json') {
            return;
        }

        setJsonDraft(safeJsonStringify(value));
        setJsonError('');
    }, [literalType, value]);

    if (literalType === 'number') {
        return (
            <input
                type="number"
                value={Number.isFinite(Number(value)) ? String(value) : '0'}
                disabled={readOnly}
                onChange={(event) => {
                    const nextValue = Number(event.target.value);
                    onChange(Number.isFinite(nextValue) ? nextValue : 0);
                }}
            />
        );
    }

    if (literalType === 'boolean') {
        return (
            <select
                value={value ? 'true' : 'false'}
                disabled={readOnly}
                onChange={(event) => onChange(event.target.value === 'true')}
            >
                <option value="true">true</option>
                <option value="false">false</option>
            </select>
        );
    }

    if (literalType === 'null') {
        return <div className="automation-dag-info-caption">null</div>;
    }

    if (literalType === 'string') {
        return (
            <input
                type="text"
                value={typeof value === 'string' ? value : ''}
                disabled={readOnly}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }

    return (
        <div className="automation-dag-modal-grid">
            <textarea
                rows={3}
                className="automation-dag-code-editor"
                value={jsonDraft}
                disabled={readOnly}
                onChange={(event) => {
                    const nextDraft = event.target.value;
                    setJsonDraft(nextDraft);

                    try {
                        const parsed = JSON.parse(nextDraft);
                        setJsonError('');
                        onChange(parsed);
                    } catch {
                        setJsonError('Invalid JSON literal.');
                    }
                }}
            />
            {jsonError !== '' ? <span className="automation-dag-field-error">{jsonError}</span> : null}
        </div>
    );
}

function VariablePathControl({ value, onChange, variableTokens, readOnly }) {
    const tokenMap = useMemo(() => {
        const map = {};

        variableTokens.forEach((token) => {
            const tokenValue = typeof token?.value === 'string'
                ? token.value
                : typeof token?.token === 'string'
                    ? token.token
                    : null;

            if (!tokenValue) {
                return;
            }

            map[tokenValue] = token;
        });

        return map;
    }, [variableTokens]);

    const selectValue = Object.prototype.hasOwnProperty.call(tokenMap, value) ? value : '__custom__';

    return (
        <div className="automation-dag-modal-grid">
            <select
                value={selectValue}
                disabled={readOnly}
                onChange={(event) => {
                    const nextValue = event.target.value;

                    if (nextValue === '__custom__') {
                        return;
                    }

                    onChange(nextValue);
                }}
            >
                {variableTokens.map((token) => {
                    const tokenValue = typeof token?.value === 'string'
                        ? token.value
                        : typeof token?.token === 'string'
                            ? token.token
                            : '';
                    const tokenLabel = typeof token?.label === 'string'
                        ? token.label
                        : tokenValue;

                    if (tokenValue === '') {
                        return null;
                    }

                    return (
                        <option key={`token-${tokenValue}`} value={tokenValue}>
                            {tokenLabel}
                        </option>
                    );
                })}
                <option value="__custom__">Custom Path</option>
            </select>

            <input
                type="text"
                value={typeof value === 'string' ? value : ''}
                disabled={readOnly}
                placeholder="trigger.value"
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function VariableListEditor({ values, onChange, variableTokens, readOnly }) {
    const listValues = Array.isArray(values) ? values : [];

    return (
        <div className="automation-dag-modal-grid">
            {listValues.map((entry, index) => (
                <div key={`var-list-${index}`} className="automation-dag-json-rule-row">
                    <VariablePathControl
                        value={typeof entry === 'string' ? entry : ''}
                        variableTokens={variableTokens}
                        readOnly={readOnly}
                        onChange={(nextPath) => {
                            const nextValues = [...listValues];
                            nextValues[index] = nextPath;
                            onChange(nextValues);
                        }}
                    />

                    <button
                        type="button"
                        className="automation-dag-action automation-dag-action-secondary"
                        disabled={readOnly || listValues.length <= 1}
                        onClick={() => {
                            const nextValues = listValues.filter((_, entryIndex) => entryIndex !== index);
                            onChange(nextValues.length > 0 ? nextValues : ['trigger.value']);
                        }}
                    >
                        Remove
                    </button>
                </div>
            ))}

            <button
                type="button"
                className="automation-dag-action automation-dag-action-secondary"
                disabled={readOnly}
                onClick={() => {
                    onChange([...listValues, 'trigger.value']);
                }}
            >
                Add Variable
            </button>
        </div>
    );
}

function OperandValueEditor({
    value,
    onChange,
    readOnly,
    variableTokens,
    operatorDefinitions,
    operatorDefinitionMap,
    depth = 0,
}) {
    const isNestedExpression = isExpressionSupportedByDefinitions(value, operatorDefinitionMap);

    if (isNestedExpression) {
        return (
            <OperatorEditor
                expression={value}
                onChange={onChange}
                readOnly={readOnly}
                variableTokens={variableTokens}
                operatorDefinitions={operatorDefinitions}
                operatorDefinitionMap={operatorDefinitionMap}
                depth={depth + 1}
            />
        );
    }

    if (isPlainObject(value) && getExpressionOperator(value) !== null) {
        return (
            <div className="automation-dag-info-panel">
                <strong>Unsupported Nested Expression</strong>
                <div>{readOnlyUnsupportedMessage(getExpressionOperator(value))}</div>
                <CodePreview value={value} />
            </div>
        );
    }

    return (
        <div className="automation-dag-modal-grid">
            <div className="automation-dag-json-operand-actions">
                <button
                    type="button"
                    className="automation-dag-action automation-dag-action-secondary"
                    disabled={readOnly}
                    onClick={() => onChange(buildDefaultExpression('var'))}
                >
                    Use Variable
                </button>
                <button
                    type="button"
                    className="automation-dag-action automation-dag-action-secondary"
                    disabled={readOnly}
                    onClick={() => onChange(buildDefaultExpression('and'))}
                >
                    Use Expression
                </button>
            </div>
            <JsonLiteralEditor
                value={value}
                readOnly={readOnly}
                onChange={onChange}
            />
        </div>
    );
}

function CodePreview({ value }) {
    return (
        <pre className="automation-dag-json-preview">{safeJsonStringify(value)}</pre>
    );
}

function OperatorEditor({
    expression,
    onChange,
    readOnly,
    variableTokens,
    operatorDefinitions,
    operatorDefinitionMap,
    depth = 0,
}) {
    const operator = getExpressionOperator(expression);

    if (typeof operator !== 'string') {
        return (
            <div className="automation-dag-info-panel">
                <strong>Invalid Expression</strong>
                <CodePreview value={expression} />
            </div>
        );
    }

    const definition = operatorDefinitionMap[operator] ?? null;

    if (!definition) {
        return (
            <div className="automation-dag-info-panel">
                <strong>Unsupported Operator</strong>
                <div>{readOnlyUnsupportedMessage(operator)}</div>
                <CodePreview value={expression} />
            </div>
        );
    }

    const rawArguments = getOperatorArguments(operator, expression[operator]);
    const args = normalizeArgumentCount(operator, rawArguments, operatorDefinitionMap);
    const minArgs = Number.isFinite(Number(definition.minArgs)) ? Number(definition.minArgs) : 1;
    const maxArgs = Number.isFinite(Number(definition.maxArgs)) ? Number(definition.maxArgs) : null;

    const updateArgs = (nextArgs) => {
        onChange(buildExpressionWithArgs(operator, nextArgs));
    };

    const updateOperator = (nextOperator) => {
        const fallbackExpression = buildDefaultExpression(nextOperator);
        onChange(fallbackExpression);
    };

    const canAddArgument = !readOnly && definition.arity === 'variadic' && (maxArgs === null || args.length < maxArgs);

    return (
        <div className={`automation-dag-json-rule ${depth > 0 ? 'is-nested' : ''}`}>
            <div className="automation-dag-json-rule-header">
                <label className="automation-dag-field">
                    <span>Operator</span>
                    <select
                        value={operator}
                        disabled={readOnly}
                        onChange={(event) => updateOperator(event.target.value)}
                    >
                        {operatorDefinitions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            {operator === 'var' ? (
                <div className="automation-dag-json-body">
                    <label className="automation-dag-field">
                        <span>Variable Path</span>
                        <VariablePathControl
                            value={typeof args[0] === 'string' ? args[0] : 'trigger.value'}
                            variableTokens={variableTokens}
                            readOnly={readOnly}
                            onChange={(nextPath) => {
                                const nextArgs = [nextPath];

                                if (args.length >= 2) {
                                    nextArgs.push(args[1]);
                                }

                                updateArgs(nextArgs);
                            }}
                        />
                    </label>

                    {args.length >= 2 ? (
                        <label className="automation-dag-field">
                            <span>Default Value</span>
                            <JsonLiteralEditor
                                value={args[1]}
                                readOnly={readOnly}
                                onChange={(nextDefault) => {
                                    updateArgs([
                                        typeof args[0] === 'string' ? args[0] : 'trigger.value',
                                        nextDefault,
                                    ]);
                                }}
                            />
                        </label>
                    ) : null}

                    <button
                        type="button"
                        className="automation-dag-action automation-dag-action-secondary"
                        disabled={readOnly}
                        onClick={() => {
                            if (args.length >= 2) {
                                updateArgs([typeof args[0] === 'string' ? args[0] : 'trigger.value']);

                                return;
                            }

                            updateArgs([
                                typeof args[0] === 'string' ? args[0] : 'trigger.value',
                                null,
                            ]);
                        }}
                    >
                        {args.length >= 2 ? 'Remove Default' : 'Add Default'}
                    </button>
                </div>
            ) : null}

            {operator === 'missing' ? (
                <label className="automation-dag-field">
                    <span>Required Variable Paths</span>
                    <VariableListEditor
                        values={args}
                        variableTokens={variableTokens}
                        readOnly={readOnly}
                        onChange={(nextValues) => updateArgs(nextValues)}
                    />
                </label>
            ) : null}

            {operator === 'missing_some' ? (
                <div className="automation-dag-json-body">
                    <label className="automation-dag-field">
                        <span>Minimum Required</span>
                        <input
                            type="number"
                            min="1"
                            value={Number.isFinite(Number(args[0])) ? String(Number(args[0])) : '1'}
                            disabled={readOnly}
                            onChange={(event) => {
                                const nextCount = Number(event.target.value);
                                updateArgs([
                                    Number.isFinite(nextCount) && nextCount > 0 ? nextCount : 1,
                                    Array.isArray(args[1]) ? args[1] : ['trigger.value', 'query.value'],
                                ]);
                            }}
                        />
                    </label>

                    <label className="automation-dag-field">
                        <span>Variable Paths</span>
                        <VariableListEditor
                            values={Array.isArray(args[1]) ? args[1] : ['trigger.value', 'query.value']}
                            variableTokens={variableTokens}
                            readOnly={readOnly}
                            onChange={(nextValues) => updateArgs([
                                Number.isFinite(Number(args[0])) ? Number(args[0]) : 1,
                                nextValues,
                            ])}
                        />
                    </label>
                </div>
            ) : null}

            {operator !== 'var' && operator !== 'missing' && operator !== 'missing_some' ? (
                <div className="automation-dag-json-body">
                    {args.map((arg, index) => (
                        <div key={`arg-${index}`} className="automation-dag-json-argument-row">
                            <div className="automation-dag-json-argument-label">Argument {index + 1}</div>
                            <OperandValueEditor
                                value={arg}
                                readOnly={readOnly}
                                variableTokens={variableTokens}
                                operatorDefinitions={operatorDefinitions}
                                operatorDefinitionMap={operatorDefinitionMap}
                                depth={depth}
                                onChange={(nextValue) => {
                                    const nextArgs = [...args];
                                    nextArgs[index] = deepCloneJsonValue(nextValue);
                                    updateArgs(nextArgs);
                                }}
                            />
                            <button
                                type="button"
                                className="automation-dag-action automation-dag-action-secondary"
                                disabled={readOnly || args.length <= minArgs}
                                onClick={() => {
                                    const nextArgs = args.filter((_, argIndex) => argIndex !== index);
                                    updateArgs(nextArgs);
                                }}
                            >
                                Remove
                            </button>
                        </div>
                    ))}

                    {canAddArgument ? (
                        <button
                            type="button"
                            className="automation-dag-action automation-dag-action-secondary"
                            onClick={() => {
                                updateArgs([...args, 0]);
                            }}
                        >
                            Add Argument
                        </button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

export function JsonLogicBuilder({
    value,
    onChange,
    variableTokens = [],
    operatorDefinitions = JSON_LOGIC_OPERATOR_DEFINITIONS,
    readOnly = false,
}) {
    const resolvedOperatorDefinitions = Array.isArray(operatorDefinitions) && operatorDefinitions.length > 0
        ? operatorDefinitions
        : JSON_LOGIC_OPERATOR_DEFINITIONS;
    const operatorDefinitionMap = useMemo(() => buildOperatorDefinitionMap(resolvedOperatorDefinitions), [resolvedOperatorDefinitions]);
    const resolvedValue = isPlainObject(value) ? value : buildDefaultExpression('>');
    const unsupportedOperators = useMemo(() => {
        return Array.from(collectUnsupportedOperatorsByDefinitions(resolvedValue, operatorDefinitionMap));
    }, [operatorDefinitionMap, resolvedValue]);

    const hasUnsupported = unsupportedOperators.length > 0;
    const shouldRenderReadOnly = readOnly || hasUnsupported;
    const resolvedTokens = Array.isArray(variableTokens) && variableTokens.length > 0
        ? variableTokens
        : [
            { label: 'Trigger Value', value: 'trigger.value' },
            { label: 'Query Value', value: 'query.value' },
            { label: 'Trigger Device ID', value: 'trigger.device_id' },
            { label: 'Run ID', value: 'run.id' },
        ];

    useEffect(() => {
        if (resolvedOperatorDefinitions.length === 0) {
            return;
        }

        const operator = getExpressionOperator(resolvedValue);

        if (typeof operator === 'string') {
            return;
        }

        onChange(buildDefaultExpression(resolvedOperatorDefinitions[0].value));
    }, [onChange, resolvedOperatorDefinitions, resolvedValue]);

    return (
        <div className="automation-dag-modal-grid">
            {hasUnsupported ? (
                <div className="automation-dag-info-panel">
                    <strong>Read-only Visual Mode</strong>
                    <div>
                        Unsupported operators detected: {unsupportedOperators.join(', ')}.
                        Use <em>Advanced JSON</em> to edit these rules.
                    </div>
                </div>
            ) : null}

            <OperatorEditor
                expression={resolvedValue}
                onChange={onChange}
                readOnly={shouldRenderReadOnly}
                variableTokens={resolvedTokens}
                operatorDefinitions={resolvedOperatorDefinitions}
                operatorDefinitionMap={operatorDefinitionMap}
                depth={0}
            />

            <div className="automation-dag-info-panel">
                <strong>Live JSON Preview</strong>
                <CodePreview value={resolvedValue} />
            </div>
        </div>
    );
}
