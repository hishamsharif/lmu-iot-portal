import React, { useEffect, useMemo, useState } from 'react';
import {
    CONDITION_LEFT_OPTIONS,
    CONDITION_OPERATOR_OPTIONS,
    JSON_LOGIC_OPERATOR_DEFINITIONS,
} from '../constants';
import { JsonLogicBuilder } from '../components/JsonLogicBuilder';
import { CodeEditorField } from '../components/CodeEditorField';
import { callLivewireMethod } from '../services/livewire';
import {
    buildGuidedJsonLogic,
    isPlainObject,
    normalizeConditionLeftOperand,
    safeJsonStringify,
} from '../logic/helpers';

function resolveJsonLogicValue(draft, fallback) {
    if (isPlainObject(draft?.json_logic_value) && Object.keys(draft.json_logic_value).length === 1) {
        return draft.json_logic_value;
    }

    if (isPlainObject(draft?.json_logic) && Object.keys(draft.json_logic).length === 1) {
        return draft.json_logic;
    }

    const textValue = typeof draft?.json_logic_text === 'string' ? draft.json_logic_text : '';

    if (textValue !== '') {
        try {
            const parsed = JSON.parse(textValue);

            if (isPlainObject(parsed) && Object.keys(parsed).length === 1) {
                return parsed;
            }
        } catch {
            return fallback;
        }
    }

    return fallback;
}

export function ConditionConfigEditor({ draft, onDraftChange, livewireId }) {
    const [templates, setTemplates] = useState({
        left_operands: CONDITION_LEFT_OPTIONS,
        operators: CONDITION_OPERATOR_OPTIONS,
        default_mode: 'guided',
        default_guided: {
            left: 'trigger.value',
            operator: '>',
            right: 240,
        },
        default_json_logic: {
            '>': [{ var: 'trigger.value' }, 240],
        },
        json_logic_operators: JSON_LOGIC_OPERATOR_DEFINITIONS,
        variable_tokens: [
            { label: 'Trigger Value', value: 'trigger.value' },
            { label: 'Query Value', value: 'query.value' },
            { label: 'Trigger Device ID', value: 'trigger.device_id' },
            { label: 'Run ID', value: 'run.id' },
        ],
    });

    useEffect(() => {
        let ignore = false;

        const loadTemplates = async () => {
            try {
                const response = await callLivewireMethod(livewireId, 'getConditionTemplates');

                if (ignore || !isPlainObject(response)) {
                    return;
                }

                setTemplates((currentTemplates) => ({
                    ...currentTemplates,
                    ...response,
                    left_operands: Array.isArray(response.left_operands) && response.left_operands.length > 0
                        ? response.left_operands
                        : currentTemplates.left_operands,
                    operators: Array.isArray(response.operators) && response.operators.length > 0
                        ? response.operators
                        : currentTemplates.operators,
                    json_logic_operators: Array.isArray(response.json_logic_operators) && response.json_logic_operators.length > 0
                        ? response.json_logic_operators
                        : currentTemplates.json_logic_operators,
                    variable_tokens: Array.isArray(response.variable_tokens) && response.variable_tokens.length > 0
                        ? response.variable_tokens
                        : currentTemplates.variable_tokens,
                }));
            } catch (error) {
                if (!ignore) {
                    console.error('Unable to load condition templates.', error);
                }
            }
        };

        loadTemplates();

        return () => {
            ignore = true;
        };
    }, [livewireId]);

    const mode = draft?.mode === 'json_logic' ? 'json_logic' : 'guided';
    const leftOperands = Array.isArray(templates.left_operands) && templates.left_operands.length > 0
        ? templates.left_operands
        : CONDITION_LEFT_OPTIONS;
    const operators = Array.isArray(templates.operators) && templates.operators.length > 0
        ? templates.operators
        : CONDITION_OPERATOR_OPTIONS;

    const guided = isPlainObject(draft?.guided)
        ? {
              left: normalizeConditionLeftOperand(draft.guided.left),
              operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === draft.guided.operator)
                  ? draft.guided.operator
                  : '>',
              right: Number.isFinite(Number(draft.guided.right)) ? Number(draft.guided.right) : 240,
          }
        : {
              left: normalizeConditionLeftOperand(templates?.default_guided?.left),
              operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === templates?.default_guided?.operator)
                  ? templates.default_guided.operator
                  : '>',
              right: Number.isFinite(Number(templates?.default_guided?.right)) ? Number(templates.default_guided.right) : 240,
          };

    const resolvedDefaultJsonLogic = isPlainObject(templates.default_json_logic)
        ? templates.default_json_logic
        : buildGuidedJsonLogic(guided);
    const jsonLogicValue = resolveJsonLogicValue(draft, resolvedDefaultJsonLogic);
    const jsonLogicText = typeof draft?.json_logic_text === 'string'
        ? draft.json_logic_text
        : safeJsonStringify(jsonLogicValue);
    const jsonEditorTab = draft?.json_logic_editor_tab === 'advanced' ? 'advanced' : 'builder';

    const jsonLogicParseError = useMemo(() => {
        if (mode !== 'json_logic') {
            return '';
        }

        try {
            const parsed = JSON.parse(jsonLogicText);

            if (!isPlainObject(parsed) || Object.keys(parsed).length !== 1) {
                return 'JSON logic must be an object with a single root operator.';
            }

            return '';
        } catch {
            return 'JSON logic is not valid JSON.';
        }
    }, [jsonLogicText, mode]);

    const applyGuided = (nextGuided) => {
        const nextJsonLogic = buildGuidedJsonLogic(nextGuided);

        onDraftChange((currentDraft) => ({
            ...currentDraft,
            mode: 'guided',
            guided: nextGuided,
            json_logic: nextJsonLogic,
            json_logic_value: nextJsonLogic,
            json_logic_text: safeJsonStringify(nextJsonLogic),
        }));
    };

    return (
        <div className="automation-dag-modal-grid">
            <div className="automation-dag-tab-bar">
                <button
                    type="button"
                    className={`automation-dag-tab ${mode === 'guided' ? 'is-active' : ''}`}
                    onClick={() => {
                        const nextGuided = {
                            left: normalizeConditionLeftOperand(guided.left),
                            operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === guided.operator)
                                ? guided.operator
                                : '>',
                            right: Number.isFinite(Number(guided.right)) ? Number(guided.right) : 240,
                        };

                        applyGuided(nextGuided);
                    }}
                >
                    Guided
                </button>

                <button
                    type="button"
                    className={`automation-dag-tab ${mode === 'json_logic' ? 'is-active' : ''}`}
                    onClick={() => {
                        onDraftChange((currentDraft) => ({
                            ...currentDraft,
                            mode: 'json_logic',
                            json_logic_value: jsonLogicValue,
                            json_logic_text: safeJsonStringify(jsonLogicValue),
                            json_logic_editor_tab: currentDraft?.json_logic_editor_tab === 'advanced' ? 'advanced' : 'builder',
                        }));
                    }}
                >
                    JSON Logic
                </button>
            </div>

            {mode === 'guided' ? (
                <div className="automation-dag-grid-two">
                    <label className="automation-dag-field">
                        <span>Left Operand</span>
                        <select
                            value={guided.left}
                            onChange={(event) => {
                                const nextLeft = normalizeConditionLeftOperand(event.target.value);

                                applyGuided({
                                    left: nextLeft,
                                    operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === guided.operator)
                                        ? guided.operator
                                        : '>',
                                    right: Number.isFinite(Number(guided.right)) ? Number(guided.right) : 240,
                                });
                            }}
                        >
                            {leftOperands.map((operand) => (
                                <option key={String(operand.value)} value={String(operand.value)}>
                                    {String(operand.label ?? operand.value)}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="automation-dag-field">
                        <span>Operator</span>
                        <select
                            value={guided.operator}
                            onChange={(event) => {
                                const nextOperator = event.target.value;

                                applyGuided({
                                    left: normalizeConditionLeftOperand(guided.left),
                                    operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === nextOperator)
                                        ? nextOperator
                                        : '>',
                                    right: Number.isFinite(Number(guided.right)) ? Number(guided.right) : 240,
                                });
                            }}
                        >
                            {operators.map((operator) => (
                                <option key={String(operator.value)} value={String(operator.value)}>
                                    {String(operator.label ?? operator.value)}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="automation-dag-field">
                        <span>Threshold</span>
                        <input
                            type="number"
                            value={String(guided.right)}
                            onChange={(event) => {
                                const nextThreshold = Number(event.target.value);

                                applyGuided({
                                    left: normalizeConditionLeftOperand(guided.left),
                                    operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === guided.operator)
                                        ? guided.operator
                                        : '>',
                                    right: Number.isFinite(nextThreshold) ? nextThreshold : 0,
                                });
                            }}
                        />
                    </label>
                </div>
            ) : (
                <div className="automation-dag-modal-grid">
                    <div className="automation-dag-tab-bar">
                        <button
                            type="button"
                            className={`automation-dag-tab ${jsonEditorTab === 'builder' ? 'is-active' : ''}`}
                            onClick={() => {
                                onDraftChange((currentDraft) => ({
                                    ...currentDraft,
                                    mode: 'json_logic',
                                    json_logic_editor_tab: 'builder',
                                }));
                            }}
                        >
                            Visual Builder
                        </button>
                        <button
                            type="button"
                            className={`automation-dag-tab ${jsonEditorTab === 'advanced' ? 'is-active' : ''}`}
                            onClick={() => {
                                onDraftChange((currentDraft) => ({
                                    ...currentDraft,
                                    mode: 'json_logic',
                                    json_logic_editor_tab: 'advanced',
                                }));
                            }}
                        >
                            Advanced JSON
                        </button>
                    </div>

                    {jsonEditorTab === 'builder' ? (
                        <JsonLogicBuilder
                            value={jsonLogicValue}
                            operatorDefinitions={templates.json_logic_operators}
                            variableTokens={templates.variable_tokens}
                            onChange={(nextExpression) => {
                                onDraftChange((currentDraft) => ({
                                    ...currentDraft,
                                    mode: 'json_logic',
                                    json_logic_value: nextExpression,
                                    json_logic_text: safeJsonStringify(nextExpression),
                                }));
                            }}
                        />
                    ) : (
                        <CodeEditorField
                            label="JSON Logic"
                            value={jsonLogicText}
                            rows={11}
                            error={jsonLogicParseError}
                            onChange={(nextJsonText) => {
                                onDraftChange((currentDraft) => {
                                    let nextJsonLogic = currentDraft?.json_logic_value;

                                    try {
                                        const parsed = JSON.parse(nextJsonText);

                                        if (isPlainObject(parsed) && Object.keys(parsed).length === 1) {
                                            nextJsonLogic = parsed;
                                        }
                                    } catch {
                                        nextJsonLogic = currentDraft?.json_logic_value;
                                    }

                                    return {
                                        ...currentDraft,
                                        mode: 'json_logic',
                                        json_logic_text: nextJsonText,
                                        json_logic_value: nextJsonLogic,
                                    };
                                });
                            }}
                        />
                    )}
                </div>
            )}
        </div>
    );
}
