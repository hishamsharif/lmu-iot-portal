import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    addEdge,
    Background,
    ConnectionLineType,
    Controls,
    Handle,
    MarkerType,
    MiniMap,
    Panel,
    Position,
    ReactFlow,
    useEdgesState,
    useNodesState,
} from '@xyflow/react';

const NODE_PALETTE = [
    { type: 'telemetry-trigger', label: 'Telemetry Trigger' },
    { type: 'schedule-trigger', label: 'Schedule Trigger' },
    { type: 'condition', label: 'Condition' },
    { type: 'delay', label: 'Delay' },
    { type: 'command', label: 'Command' },
    { type: 'alert', label: 'Alert' },
];

const CONDITION_OPERATOR_OPTIONS = [
    { value: '>', label: 'Greater than' },
    { value: '>=', label: 'Greater than or equal' },
    { value: '<', label: 'Less than' },
    { value: '<=', label: 'Less than or equal' },
    { value: '==', label: 'Equal to' },
    { value: '!=', label: 'Not equal to' },
];

const DEFAULT_VIEWPORT = { x: 0, y: 0, zoom: 1 };

function isPlainObject(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toPositiveInteger(value) {
    if (typeof value === 'number' && Number.isInteger(value) && value > 0) {
        return value;
    }

    if (typeof value === 'string' && /^\d+$/.test(value)) {
        const parsed = Number(value);

        return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
    }

    return null;
}

function detectDarkMode() {
    return document.documentElement.classList.contains('dark') || document.body?.classList.contains('dark');
}

function paletteLabel(type) {
    const match = NODE_PALETTE.find((node) => node.type === type);

    return match?.label ?? 'Node';
}

function normalizePosition(position, index) {
    if (isPlainObject(position) && Number.isFinite(position.x) && Number.isFinite(position.y)) {
        return {
            x: Number(position.x),
            y: Number(position.y),
        };
    }

    return {
        x: 160 + (index % 3) * 320,
        y: 130 + Math.floor(index / 3) * 190,
    };
}

function nodeColorForMiniMap(nodeType, isDark) {
    const palette = {
        'telemetry-trigger': isDark ? '#fbbf24' : '#f59e0b',
        'schedule-trigger': isDark ? '#fbbf24' : '#f59e0b',
        condition: isDark ? '#60a5fa' : '#2563eb',
        delay: isDark ? '#a78bfa' : '#7c3aed',
        command: isDark ? '#34d399' : '#059669',
        alert: isDark ? '#f87171' : '#dc2626',
    };

    return palette[nodeType] ?? (isDark ? '#94a3b8' : '#64748b');
}

function safeJsonStringify(value, spacing = 2) {
    try {
        return JSON.stringify(value, null, spacing);
    } catch {
        return '{}';
    }
}

function compactJson(value) {
    try {
        return JSON.stringify(value);
    } catch {
        return '{}';
    }
}

function truncate(value, maxLength = 90) {
    if (typeof value !== 'string') {
        return '';
    }

    if (value.length <= maxLength) {
        return value;
    }

    return `${value.slice(0, maxLength - 3)}...`;
}

function buildGuidedJsonLogic(guided) {
    return {
        [guided.operator]: [{ var: guided.left }, guided.right],
    };
}

function createDefaultConfigDraft(nodeType, existingConfig) {
    if (nodeType === 'telemetry-trigger') {
        const source = isPlainObject(existingConfig?.source) ? existingConfig.source : {};

        return {
            mode: 'event',
            source: {
                device_id: toPositiveInteger(source.device_id),
                topic_id: toPositiveInteger(source.topic_id),
                parameter_definition_id: toPositiveInteger(source.parameter_definition_id),
            },
        };
    }

    if (nodeType === 'condition') {
        const mode = existingConfig?.mode === 'json_logic' ? 'json_logic' : 'guided';
        const existingGuided = isPlainObject(existingConfig?.guided) ? existingConfig.guided : {};
        const guided = {
            left: existingGuided.left === 'trigger.value' ? 'trigger.value' : 'trigger.value',
            operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === existingGuided.operator)
                ? existingGuided.operator
                : '>',
            right: Number.isFinite(Number(existingGuided.right)) ? Number(existingGuided.right) : 240,
        };

        const existingJsonLogic = isPlainObject(existingConfig?.json_logic)
            ? existingConfig.json_logic
            : buildGuidedJsonLogic(guided);

        return {
            mode,
            guided,
            json_logic: existingJsonLogic,
            json_logic_text: safeJsonStringify(existingJsonLogic),
        };
    }

    if (nodeType === 'command') {
        const target = isPlainObject(existingConfig?.target) ? existingConfig.target : {};

        return {
            target: {
                device_id: toPositiveInteger(target.device_id),
                topic_id: toPositiveInteger(target.topic_id),
            },
            payload_mode: 'schema_form',
            payload: isPlainObject(existingConfig?.payload) ? { ...existingConfig.payload } : {},
        };
    }

    return {
        generic_json_text: safeJsonStringify(isPlainObject(existingConfig) ? existingConfig : {}),
    };
}

function normalizeConfigForSave(nodeType, draft) {
    if (nodeType === 'telemetry-trigger') {
        const deviceId = toPositiveInteger(draft?.source?.device_id);
        const topicId = toPositiveInteger(draft?.source?.topic_id);
        const parameterDefinitionId = toPositiveInteger(draft?.source?.parameter_definition_id);

        if (!deviceId || !topicId || !parameterDefinitionId) {
            throw new Error('Telemetry trigger requires source device, topic, and parameter.');
        }

        return {
            mode: 'event',
            source: {
                device_id: deviceId,
                topic_id: topicId,
                parameter_definition_id: parameterDefinitionId,
            },
        };
    }

    if (nodeType === 'condition') {
        const mode = draft?.mode === 'json_logic' ? 'json_logic' : 'guided';

        if (mode === 'guided') {
            const guided = {
                left: 'trigger.value',
                operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === draft?.guided?.operator)
                    ? draft.guided.operator
                    : '>',
                right: Number(draft?.guided?.right),
            };

            if (!Number.isFinite(guided.right)) {
                throw new Error('Condition threshold must be numeric.');
            }

            return {
                mode: 'guided',
                guided,
                json_logic: buildGuidedJsonLogic(guided),
            };
        }

        let parsedJsonLogic;

        try {
            parsedJsonLogic = JSON.parse(typeof draft?.json_logic_text === 'string' ? draft.json_logic_text : '{}');
        } catch {
            throw new Error('Advanced JSON logic must be valid JSON.');
        }

        if (!isPlainObject(parsedJsonLogic) || Object.keys(parsedJsonLogic).length !== 1) {
            throw new Error('Advanced JSON logic must be an object with a single root operator.');
        }

        return {
            mode: 'json_logic',
            guided: {
                left: 'trigger.value',
                operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === draft?.guided?.operator)
                    ? draft.guided.operator
                    : '>',
                right: Number.isFinite(Number(draft?.guided?.right)) ? Number(draft.guided.right) : 240,
            },
            json_logic: parsedJsonLogic,
        };
    }

    if (nodeType === 'command') {
        const targetDeviceId = toPositiveInteger(draft?.target?.device_id);
        const targetTopicId = toPositiveInteger(draft?.target?.topic_id);
        const payload = isPlainObject(draft?.payload) ? draft.payload : null;

        if (!targetDeviceId || !targetTopicId) {
            throw new Error('Command node requires target device and topic.');
        }

        if (!payload) {
            throw new Error('Command payload must be an object.');
        }

        return {
            target: {
                device_id: targetDeviceId,
                topic_id: targetTopicId,
            },
            payload,
            payload_mode: 'schema_form',
        };
    }

    let genericConfig;

    try {
        genericConfig = JSON.parse(typeof draft?.generic_json_text === 'string' ? draft.generic_json_text : '{}');
    } catch {
        throw new Error('Generic node configuration must be valid JSON.');
    }

    if (!isPlainObject(genericConfig)) {
        throw new Error('Generic node configuration must be a JSON object.');
    }

    return genericConfig;
}

function summarizeNodeConfig(nodeType, config) {
    if (!isPlainObject(config)) {
        return '';
    }

    if (nodeType === 'telemetry-trigger') {
        const source = isPlainObject(config.source) ? config.source : {};
        const deviceId = toPositiveInteger(source.device_id);
        const topicId = toPositiveInteger(source.topic_id);
        const parameterDefinitionId = toPositiveInteger(source.parameter_definition_id);

        if (!deviceId || !topicId || !parameterDefinitionId) {
            return 'Not configured';
        }

        return `Device #${deviceId} / Topic #${topicId} / Param #${parameterDefinitionId}`;
    }

    if (nodeType === 'condition') {
        if (config.mode === 'guided' && isPlainObject(config.guided)) {
            const operator = typeof config.guided.operator === 'string' ? config.guided.operator : '>';
            const right = Number(config.guided.right);
            const resolvedRight = Number.isFinite(right) ? right : '?';

            return `trigger.value ${operator} ${resolvedRight}`;
        }

        if (isPlainObject(config.json_logic)) {
            return truncate(compactJson(config.json_logic), 84);
        }

        return 'Not configured';
    }

    if (nodeType === 'command') {
        const target = isPlainObject(config.target) ? config.target : {};
        const deviceId = toPositiveInteger(target.device_id);
        const topicId = toPositiveInteger(target.topic_id);
        const payloadKeys = isPlainObject(config.payload) ? Object.keys(config.payload) : [];
        const payloadPreview = payloadKeys.length > 0 ? payloadKeys.slice(0, 3).join(', ') : 'no payload';

        if (!deviceId || !topicId) {
            return 'Not configured';
        }

        return `Target #${deviceId} / Topic #${topicId} / ${payloadPreview}`;
    }

    const keys = Object.keys(config);

    return keys.length > 0 ? `Configured (${keys.length} keys)` : 'Not configured';
}

function parseInitialGraph(rawGraph) {
    if (typeof rawGraph !== 'string' || rawGraph.trim() === '') {
        return {
            version: 1,
            nodes: [],
            edges: [],
            viewport: DEFAULT_VIEWPORT,
        };
    }

    try {
        const graph = JSON.parse(rawGraph);

        return {
            version: Number.isFinite(Number(graph.version)) ? Number(graph.version) : 1,
            nodes: Array.isArray(graph.nodes) ? graph.nodes.map((node, index) => normalizeNode(node, index)) : [],
            edges: Array.isArray(graph.edges) ? graph.edges.map((edge, index) => normalizeEdge(edge, index)) : [],
            viewport: isPlainObject(graph.viewport)
                ? {
                      x: Number.isFinite(Number(graph.viewport.x)) ? Number(graph.viewport.x) : DEFAULT_VIEWPORT.x,
                      y: Number.isFinite(Number(graph.viewport.y)) ? Number(graph.viewport.y) : DEFAULT_VIEWPORT.y,
                      zoom: Number.isFinite(Number(graph.viewport.zoom)) ? Number(graph.viewport.zoom) : DEFAULT_VIEWPORT.zoom,
                  }
                : DEFAULT_VIEWPORT,
        };
    } catch {
        return {
            version: 1,
            nodes: [],
            edges: [],
            viewport: DEFAULT_VIEWPORT,
        };
    }
}

function normalizeNode(node, index) {
    const rawType = typeof node?.type === 'string' && node.type !== '' ? node.type : 'condition';
    const nodeType = NODE_PALETTE.some((candidate) => candidate.type === rawType) ? rawType : 'condition';
    const label = paletteLabel(nodeType);
    const identifier = typeof node?.id === 'string' && node.id !== '' ? node.id : `${nodeType}-${index + 1}`;
    const existingData = isPlainObject(node?.data) ? node.data : {};

    return {
        id: identifier,
        type: 'workflowNode',
        position: normalizePosition(node?.position, index),
        data: {
            ...existingData,
            nodeType,
            label:
                typeof existingData.label === 'string' && existingData.label !== ''
                    ? existingData.label
                    : label,
            summary:
                typeof existingData.summary === 'string'
                    ? existingData.summary
                    : summarizeNodeConfig(nodeType, existingData.config),
        },
    };
}

function normalizeEdge(edge, index) {
    const source = typeof edge?.source === 'string' ? edge.source : '';
    const target = typeof edge?.target === 'string' ? edge.target : '';

    return {
        id: typeof edge?.id === 'string' && edge.id !== '' ? edge.id : `edge-${index + 1}`,
        source,
        target,
        type: typeof edge?.type === 'string' && edge.type !== '' ? edge.type : 'smoothstep',
        markerEnd: { type: MarkerType.ArrowClosed },
    };
}

function buildGraphPayload(nodes, edges, viewport) {
    return {
        version: 1,
        nodes: nodes.map((node) => ({
            id: node.id,
            type: node.data?.nodeType ?? 'condition',
            data: {
                ...(isPlainObject(node.data) ? node.data : {}),
            },
            position: {
                x: Number(node.position?.x ?? 0),
                y: Number(node.position?.y ?? 0),
            },
        })),
        edges: edges.map((edge, index) => ({
            id: edge.id ?? `edge-${index + 1}`,
            source: edge.source,
            target: edge.target,
            type: edge.type ?? 'smoothstep',
        })),
        viewport: {
            x: Number(viewport?.x ?? 0),
            y: Number(viewport?.y ?? 0),
            zoom: Number(viewport?.zoom ?? 1),
        },
    };
}

async function callLivewireMethod(livewireId, methodName, payload = undefined) {
    if (typeof window.Livewire === 'undefined' || livewireId === '') {
        return null;
    }

    const livewireComponent = window.Livewire.find(livewireId);
    if (!livewireComponent) {
        return null;
    }

    if (typeof payload === 'undefined') {
        return livewireComponent.call(methodName);
    }

    return livewireComponent.call(methodName, payload);
}

function WorkflowNodeCard({ data, selected }) {
    const nodeType = typeof data?.nodeType === 'string' ? data.nodeType : 'condition';
    const label = typeof data?.label === 'string' && data.label !== '' ? data.label : paletteLabel(nodeType);
    const summary = typeof data?.summary === 'string' ? data.summary : '';
    const isTrigger = nodeType.endsWith('trigger');
    const isTerminal = nodeType === 'command' || nodeType === 'alert';

    return (
        <div
            className={`automation-dag-node ${selected ? 'is-selected' : ''}`}
            data-node-type={nodeType}
        >
            {!isTrigger ? <Handle type="target" position={Position.Left} className="automation-dag-handle" /> : null}

            <div className="automation-dag-node-chip">{paletteLabel(nodeType)}</div>
            <div className="automation-dag-node-title">{label}</div>
            <div className="automation-dag-node-summary">{summary !== '' ? summary : 'Double-click to configure'}</div>

            {!isTerminal ? <Handle type="source" position={Position.Right} className="automation-dag-handle" /> : null}
        </div>
    );
}

const NODE_TYPES = {
    workflowNode: WorkflowNodeCard,
};

function TelemetryTriggerConfigEditor({ draft, onDraftChange, livewireId }) {
    const source = isPlainObject(draft?.source) ? draft.source : {};
    const selectedDeviceId = toPositiveInteger(source.device_id);
    const selectedTopicId = toPositiveInteger(source.topic_id);
    const selectedParameterDefinitionId = toPositiveInteger(source.parameter_definition_id);

    const [options, setOptions] = useState({ devices: [], topics: [], parameters: [] });
    const [isLoadingOptions, setIsLoadingOptions] = useState(false);
    const [preview, setPreview] = useState(null);
    const [isLoadingPreview, setIsLoadingPreview] = useState(false);

    const updateSource = useCallback(
        (nextValues) => {
            onDraftChange((currentDraft) => {
                const currentSource = isPlainObject(currentDraft?.source) ? currentDraft.source : {};

                return {
                    ...currentDraft,
                    mode: 'event',
                    source: {
                        ...currentSource,
                        ...nextValues,
                    },
                };
            });
        },
        [onDraftChange],
    );

    useEffect(() => {
        let ignore = false;

        const loadOptions = async () => {
            setIsLoadingOptions(true);

            try {
                const response = await callLivewireMethod(livewireId, 'getTelemetryTriggerOptions', {
                    device_id: selectedDeviceId,
                    topic_id: selectedTopicId,
                });

                if (ignore || !isPlainObject(response)) {
                    return;
                }

                setOptions({
                    devices: Array.isArray(response.devices) ? response.devices : [],
                    topics: Array.isArray(response.topics) ? response.topics : [],
                    parameters: Array.isArray(response.parameters) ? response.parameters : [],
                });
            } catch (error) {
                if (!ignore) {
                    console.error('Unable to load telemetry trigger options.', error);
                }
            } finally {
                if (!ignore) {
                    setIsLoadingOptions(false);
                }
            }
        };

        loadOptions();

        return () => {
            ignore = true;
        };
    }, [livewireId, selectedDeviceId, selectedTopicId]);

    useEffect(() => {
        let ignore = false;

        if (!selectedDeviceId || !selectedTopicId || !selectedParameterDefinitionId) {
            setPreview(null);

            return () => {
                ignore = true;
            };
        }

        const loadPreview = async () => {
            setIsLoadingPreview(true);

            try {
                const response = await callLivewireMethod(livewireId, 'previewLatestTelemetryValue', {
                    device_id: selectedDeviceId,
                    topic_id: selectedTopicId,
                    parameter_definition_id: selectedParameterDefinitionId,
                });

                if (!ignore) {
                    setPreview(isPlainObject(response) ? response : null);
                }
            } catch (error) {
                if (!ignore) {
                    console.error('Unable to preview latest telemetry value.', error);
                }
            } finally {
                if (!ignore) {
                    setIsLoadingPreview(false);
                }
            }
        };

        loadPreview();

        return () => {
            ignore = true;
        };
    }, [livewireId, selectedDeviceId, selectedParameterDefinitionId, selectedTopicId]);

    const previewValue = isPlainObject(preview) ? preview.value : null;
    const previewRecordedAt = isPlainObject(preview) && typeof preview.recorded_at === 'string' ? preview.recorded_at : null;

    return (
        <div className="automation-dag-modal-grid">
            <label className="automation-dag-field">
                <span>Source Device</span>
                <select
                    value={selectedDeviceId ?? ''}
                    onChange={(event) => {
                        const nextDeviceId = toPositiveInteger(event.target.value);

                        updateSource({
                            device_id: nextDeviceId,
                            topic_id: null,
                            parameter_definition_id: null,
                        });
                    }}
                >
                    <option value="">Select device</option>
                    {options.devices.map((device) => (
                        <option key={String(device.id)} value={String(device.id)}>
                            {String(device.label ?? `Device #${device.id}`)}
                        </option>
                    ))}
                </select>
            </label>

            <label className="automation-dag-field">
                <span>Source Topic</span>
                <select
                    value={selectedTopicId ?? ''}
                    onChange={(event) => {
                        const nextTopicId = toPositiveInteger(event.target.value);

                        updateSource({
                            topic_id: nextTopicId,
                            parameter_definition_id: null,
                        });
                    }}
                    disabled={!selectedDeviceId}
                >
                    <option value="">Select publish topic</option>
                    {options.topics.map((topic) => (
                        <option key={String(topic.id)} value={String(topic.id)}>
                            {String(topic.label ?? `Topic #${topic.id}`)}
                        </option>
                    ))}
                </select>
            </label>

            <label className="automation-dag-field">
                <span>Source Parameter</span>
                <select
                    value={selectedParameterDefinitionId ?? ''}
                    onChange={(event) => {
                        const nextParameterDefinitionId = toPositiveInteger(event.target.value);

                        updateSource({
                            parameter_definition_id: nextParameterDefinitionId,
                        });
                    }}
                    disabled={!selectedTopicId}
                >
                    <option value="">Select parameter</option>
                    {options.parameters.map((parameter) => (
                        <option key={String(parameter.id)} value={String(parameter.id)}>
                            {String(parameter.label ?? parameter.key ?? `Parameter #${parameter.id}`)}
                        </option>
                    ))}
                </select>
            </label>

            <div className="automation-dag-info-panel">
                <strong>Latest Value Preview</strong>
                {isLoadingPreview ? <div>Loading latest value...</div> : null}
                {!isLoadingPreview && !preview ? <div>No telemetry value found yet.</div> : null}
                {!isLoadingPreview && preview ? (
                    <div>
                        <div className="automation-dag-info-value">
                            {typeof previewValue === 'object' ? compactJson(previewValue) : String(previewValue)}
                        </div>
                        {previewRecordedAt ? (
                            <div className="automation-dag-info-caption">Recorded: {new Date(previewRecordedAt).toLocaleString()}</div>
                        ) : null}
                    </div>
                ) : null}
                {isLoadingOptions ? <div className="automation-dag-info-caption">Refreshing source options...</div> : null}
            </div>
        </div>
    );
}

function ConditionConfigEditor({ draft, onDraftChange, livewireId }) {
    const [templates, setTemplates] = useState({
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
                    operators: Array.isArray(response.operators) && response.operators.length > 0
                        ? response.operators
                        : currentTemplates.operators,
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
    const guided = isPlainObject(draft?.guided)
        ? {
              left: 'trigger.value',
              operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === draft.guided.operator)
                  ? draft.guided.operator
                  : '>',
              right: Number.isFinite(Number(draft.guided.right)) ? Number(draft.guided.right) : 240,
          }
        : {
              left: 'trigger.value',
              operator: '>',
              right: 240,
          };

    const jsonLogicText =
        typeof draft?.json_logic_text === 'string'
            ? draft.json_logic_text
            : safeJsonStringify(isPlainObject(draft?.json_logic) ? draft.json_logic : templates.default_json_logic);

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

    const operators = Array.isArray(templates.operators) && templates.operators.length > 0
        ? templates.operators
        : CONDITION_OPERATOR_OPTIONS;

    return (
        <div className="automation-dag-modal-grid">
            <div className="automation-dag-tab-bar">
                <button
                    type="button"
                    className={`automation-dag-tab ${mode === 'guided' ? 'is-active' : ''}`}
                    onClick={() => {
                        onDraftChange((currentDraft) => {
                            const currentGuided = isPlainObject(currentDraft?.guided)
                                ? currentDraft.guided
                                : {
                                      left: 'trigger.value',
                                      operator: '>',
                                      right: 240,
                                  };

                            const nextJsonLogic = buildGuidedJsonLogic({
                                left: 'trigger.value',
                                operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === currentGuided.operator)
                                    ? currentGuided.operator
                                    : '>',
                                right: Number.isFinite(Number(currentGuided.right)) ? Number(currentGuided.right) : 240,
                            });

                            return {
                                ...currentDraft,
                                mode: 'guided',
                                guided: {
                                    left: 'trigger.value',
                                    operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === currentGuided.operator)
                                        ? currentGuided.operator
                                        : '>',
                                    right: Number.isFinite(Number(currentGuided.right)) ? Number(currentGuided.right) : 240,
                                },
                                json_logic: nextJsonLogic,
                                json_logic_text: safeJsonStringify(nextJsonLogic),
                            };
                        });
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
                            json_logic_text:
                                typeof currentDraft?.json_logic_text === 'string'
                                    ? currentDraft.json_logic_text
                                    : safeJsonStringify(
                                          isPlainObject(currentDraft?.json_logic)
                                              ? currentDraft.json_logic
                                              : templates.default_json_logic,
                                      ),
                        }));
                    }}
                >
                    Advanced JSON
                </button>
            </div>

            {mode === 'guided' ? (
                <div className="automation-dag-grid-two">
                    <label className="automation-dag-field">
                        <span>Left Operand</span>
                        <input type="text" value="trigger.value" disabled />
                    </label>

                    <label className="automation-dag-field">
                        <span>Operator</span>
                        <select
                            value={guided.operator}
                            onChange={(event) => {
                                const nextOperator = event.target.value;

                                onDraftChange((currentDraft) => {
                                    const nextGuided = {
                                        left: 'trigger.value',
                                        operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === nextOperator)
                                            ? nextOperator
                                            : '>',
                                        right: Number.isFinite(Number(currentDraft?.guided?.right))
                                            ? Number(currentDraft.guided.right)
                                            : 240,
                                    };
                                    const nextJsonLogic = buildGuidedJsonLogic(nextGuided);

                                    return {
                                        ...currentDraft,
                                        mode: 'guided',
                                        guided: nextGuided,
                                        json_logic: nextJsonLogic,
                                        json_logic_text: safeJsonStringify(nextJsonLogic),
                                    };
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
                                onDraftChange((currentDraft) => {
                                    const nextThreshold = Number(event.target.value);
                                    const nextGuided = {
                                        left: 'trigger.value',
                                        operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === currentDraft?.guided?.operator)
                                            ? currentDraft.guided.operator
                                            : '>',
                                        right: Number.isFinite(nextThreshold) ? nextThreshold : 0,
                                    };
                                    const nextJsonLogic = buildGuidedJsonLogic(nextGuided);

                                    return {
                                        ...currentDraft,
                                        mode: 'guided',
                                        guided: nextGuided,
                                        json_logic: nextJsonLogic,
                                        json_logic_text: safeJsonStringify(nextJsonLogic),
                                    };
                                });
                            }}
                        />
                    </label>
                </div>
            ) : (
                <label className="automation-dag-field">
                    <span>JSON Logic</span>
                    <textarea
                        value={jsonLogicText}
                        onChange={(event) => {
                            onDraftChange((currentDraft) => ({
                                ...currentDraft,
                                mode: 'json_logic',
                                json_logic_text: event.target.value,
                            }));
                        }}
                        rows={11}
                    />
                    {jsonLogicParseError !== '' ? (
                        <span className="automation-dag-field-error">{jsonLogicParseError}</span>
                    ) : null}
                </label>
            )}
        </div>
    );
}

function CommandConfigEditor({ draft, onDraftChange, livewireId }) {
    const target = isPlainObject(draft?.target) ? draft.target : {};
    const selectedDeviceId = toPositiveInteger(target.device_id);
    const selectedTopicId = toPositiveInteger(target.topic_id);
    const payload = isPlainObject(draft?.payload) ? draft.payload : {};

    const [options, setOptions] = useState({ devices: [], topics: [], parameters: [] });
    const [isLoadingOptions, setIsLoadingOptions] = useState(false);
    const [jsonFieldDrafts, setJsonFieldDrafts] = useState({});
    const [jsonFieldErrors, setJsonFieldErrors] = useState({});

    const updateTarget = useCallback(
        (nextValues) => {
            onDraftChange((currentDraft) => {
                const currentTarget = isPlainObject(currentDraft?.target) ? currentDraft.target : {};

                return {
                    ...currentDraft,
                    target: {
                        ...currentTarget,
                        ...nextValues,
                    },
                    payload_mode: 'schema_form',
                    payload: isPlainObject(currentDraft?.payload) ? currentDraft.payload : {},
                };
            });
        },
        [onDraftChange],
    );

    const updatePayload = useCallback(
        (parameterKey, parameterValue) => {
            onDraftChange((currentDraft) => {
                const currentPayload = isPlainObject(currentDraft?.payload) ? currentDraft.payload : {};

                return {
                    ...currentDraft,
                    payload_mode: 'schema_form',
                    payload: {
                        ...currentPayload,
                        [parameterKey]: parameterValue,
                    },
                };
            });
        },
        [onDraftChange],
    );

    useEffect(() => {
        let ignore = false;

        const loadOptions = async () => {
            setIsLoadingOptions(true);

            try {
                const response = await callLivewireMethod(livewireId, 'getCommandNodeOptions', {
                    device_id: selectedDeviceId,
                    topic_id: selectedTopicId,
                });

                if (ignore || !isPlainObject(response)) {
                    return;
                }

                const nextOptions = {
                    devices: Array.isArray(response.devices) ? response.devices : [],
                    topics: Array.isArray(response.topics) ? response.topics : [],
                    parameters: Array.isArray(response.parameters) ? response.parameters : [],
                };

                setOptions(nextOptions);

                if (nextOptions.parameters.length > 0) {
                    onDraftChange((currentDraft) => {
                        const currentPayload = isPlainObject(currentDraft?.payload) ? { ...currentDraft.payload } : {};
                        let changed = false;

                        nextOptions.parameters.forEach((parameter) => {
                            const parameterKey = typeof parameter.key === 'string' ? parameter.key : null;

                            if (!parameterKey || Object.prototype.hasOwnProperty.call(currentPayload, parameterKey)) {
                                return;
                            }

                            if (Object.prototype.hasOwnProperty.call(parameter, 'default')) {
                                currentPayload[parameterKey] = parameter.default;
                                changed = true;
                            }
                        });

                        if (!changed) {
                            return currentDraft;
                        }

                        return {
                            ...currentDraft,
                            payload: currentPayload,
                        };
                    });
                }
            } catch (error) {
                if (!ignore) {
                    console.error('Unable to load command node options.', error);
                }
            } finally {
                if (!ignore) {
                    setIsLoadingOptions(false);
                }
            }
        };

        loadOptions();

        return () => {
            ignore = true;
        };
    }, [livewireId, onDraftChange, selectedDeviceId, selectedTopicId]);

    useEffect(() => {
        setJsonFieldDrafts((currentDrafts) => {
            const nextDrafts = { ...currentDrafts };
            let hasChanges = false;

            options.parameters.forEach((parameter) => {
                const parameterKey = typeof parameter.key === 'string' ? parameter.key : null;
                const widget = typeof parameter.widget === 'string' ? parameter.widget : null;
                const type = typeof parameter.type === 'string' ? parameter.type : null;

                if (!parameterKey || (widget !== 'json' && type !== 'json')) {
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(nextDrafts, parameterKey)) {
                    return;
                }

                const payloadValue = Object.prototype.hasOwnProperty.call(payload, parameterKey)
                    ? payload[parameterKey]
                    : parameter.default ?? {};

                nextDrafts[parameterKey] = safeJsonStringify(payloadValue);
                hasChanges = true;
            });

            return hasChanges ? nextDrafts : currentDrafts;
        });
    }, [options.parameters, payload]);

    const payloadPreview = safeJsonStringify(payload);

    return (
        <div className="automation-dag-modal-grid">
            <div className="automation-dag-grid-two">
                <label className="automation-dag-field">
                    <span>Target Device</span>
                    <select
                        value={selectedDeviceId ?? ''}
                        onChange={(event) => {
                            const nextDeviceId = toPositiveInteger(event.target.value);

                            updateTarget({
                                device_id: nextDeviceId,
                                topic_id: null,
                            });

                            onDraftChange((currentDraft) => ({
                                ...currentDraft,
                                payload: {},
                            }));

                            setJsonFieldDrafts({});
                            setJsonFieldErrors({});
                        }}
                    >
                        <option value="">Select device</option>
                        {options.devices.map((device) => (
                            <option key={String(device.id)} value={String(device.id)}>
                                {String(device.label ?? `Device #${device.id}`)}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="automation-dag-field">
                    <span>Command Topic</span>
                    <select
                        value={selectedTopicId ?? ''}
                        onChange={(event) => {
                            const nextTopicId = toPositiveInteger(event.target.value);

                            updateTarget({
                                topic_id: nextTopicId,
                            });

                            onDraftChange((currentDraft) => ({
                                ...currentDraft,
                                payload: {},
                            }));

                            setJsonFieldDrafts({});
                            setJsonFieldErrors({});
                        }}
                        disabled={!selectedDeviceId}
                    >
                        <option value="">Select subscribe topic</option>
                        {options.topics.map((topic) => (
                            <option key={String(topic.id)} value={String(topic.id)}>
                                {String(topic.label ?? `Topic #${topic.id}`)}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <div className="automation-dag-parameter-grid">
                {options.parameters.length === 0 ? (
                    <div className="automation-dag-empty-state">Select a target topic to configure payload fields.</div>
                ) : null}

                {options.parameters.map((parameter) => {
                    const parameterKey = typeof parameter.key === 'string' ? parameter.key : null;
                    const parameterLabel = typeof parameter.label === 'string' ? parameter.label : parameterKey;

                    if (!parameterKey) {
                        return null;
                    }

                    const parameterType = typeof parameter.type === 'string' ? parameter.type : 'string';
                    const widget = typeof parameter.widget === 'string' ? parameter.widget : null;
                    const currentPayloadValue = Object.prototype.hasOwnProperty.call(payload, parameterKey)
                        ? payload[parameterKey]
                        : parameter.default ?? '';

                    if (widget === 'toggle' || parameterType === 'boolean') {
                        return (
                            <label key={parameterKey} className="automation-dag-field automation-dag-field-inline">
                                <span>{parameterLabel}</span>
                                <input
                                    type="checkbox"
                                    checked={Boolean(currentPayloadValue)}
                                    onChange={(event) => updatePayload(parameterKey, event.target.checked)}
                                />
                            </label>
                        );
                    }

                    if (widget === 'select' || (isPlainObject(parameter.options) && Object.keys(parameter.options).length > 0)) {
                        const selectOptions = isPlainObject(parameter.options) ? parameter.options : {};

                        return (
                            <label key={parameterKey} className="automation-dag-field">
                                <span>{parameterLabel}</span>
                                <select
                                    value={typeof currentPayloadValue === 'string' ? currentPayloadValue : String(currentPayloadValue ?? '')}
                                    onChange={(event) => {
                                        if (parameterType === 'integer') {
                                            const nextValue = Number.parseInt(event.target.value, 10);

                                            updatePayload(parameterKey, Number.isNaN(nextValue) ? event.target.value : nextValue);

                                            return;
                                        }

                                        if (parameterType === 'decimal') {
                                            const nextValue = Number.parseFloat(event.target.value);

                                            updatePayload(parameterKey, Number.isNaN(nextValue) ? event.target.value : nextValue);

                                            return;
                                        }

                                        updatePayload(parameterKey, event.target.value);
                                    }}
                                >
                                    <option value="">Select value</option>
                                    {Object.entries(selectOptions).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {String(label)}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        );
                    }

                    if (widget === 'slider') {
                        const range = isPlainObject(parameter.range) ? parameter.range : {};
                        const min = Number.isFinite(Number(range.min)) ? Number(range.min) : 0;
                        const max = Number.isFinite(Number(range.max)) ? Number(range.max) : 100;
                        const step = Number.isFinite(Number(range.step)) ? Number(range.step) : 1;

                        return (
                            <label key={parameterKey} className="automation-dag-field">
                                <span>{parameterLabel}</span>
                                <input
                                    type="range"
                                    min={String(min)}
                                    max={String(max)}
                                    step={String(step)}
                                    value={Number.isFinite(Number(currentPayloadValue)) ? Number(currentPayloadValue) : min}
                                    onChange={(event) => {
                                        const numericValue = Number.parseFloat(event.target.value);
                                        updatePayload(parameterKey, Number.isFinite(numericValue) ? numericValue : event.target.value);
                                    }}
                                />
                                <div className="automation-dag-info-caption">{String(currentPayloadValue)}</div>
                            </label>
                        );
                    }

                    if (widget === 'number' || parameterType === 'integer' || parameterType === 'decimal') {
                        const range = isPlainObject(parameter.range) ? parameter.range : {};

                        return (
                            <label key={parameterKey} className="automation-dag-field">
                                <span>{parameterLabel}</span>
                                <input
                                    type="number"
                                    min={Number.isFinite(Number(range.min)) ? Number(range.min) : undefined}
                                    max={Number.isFinite(Number(range.max)) ? Number(range.max) : undefined}
                                    step={Number.isFinite(Number(range.step)) ? Number(range.step) : parameterType === 'decimal' ? 0.1 : 1}
                                    value={Number.isFinite(Number(currentPayloadValue)) ? Number(currentPayloadValue) : ''}
                                    onChange={(event) => {
                                        const numericValue = parameterType === 'integer'
                                            ? Number.parseInt(event.target.value, 10)
                                            : Number.parseFloat(event.target.value);

                                        updatePayload(parameterKey, Number.isFinite(numericValue) ? numericValue : event.target.value);
                                    }}
                                />
                            </label>
                        );
                    }

                    if (widget === 'color') {
                        return (
                            <label key={parameterKey} className="automation-dag-field">
                                <span>{parameterLabel}</span>
                                <input
                                    type="color"
                                    value={typeof currentPayloadValue === 'string' && /^#[0-9A-Fa-f]{6}$/.test(currentPayloadValue)
                                        ? currentPayloadValue
                                        : '#ff0000'}
                                    onChange={(event) => updatePayload(parameterKey, event.target.value)}
                                />
                            </label>
                        );
                    }

                    if (widget === 'json' || parameterType === 'json') {
                        const jsonDraft = typeof jsonFieldDrafts[parameterKey] === 'string'
                            ? jsonFieldDrafts[parameterKey]
                            : safeJsonStringify(currentPayloadValue ?? {});
                        const jsonFieldError = jsonFieldErrors[parameterKey];

                        return (
                            <label key={parameterKey} className="automation-dag-field">
                                <span>{parameterLabel}</span>
                                <textarea
                                    rows={4}
                                    value={jsonDraft}
                                    onChange={(event) => {
                                        const nextDraft = event.target.value;

                                        setJsonFieldDrafts((currentDrafts) => ({
                                            ...currentDrafts,
                                            [parameterKey]: nextDraft,
                                        }));

                                        try {
                                            const parsedJson = JSON.parse(nextDraft);

                                            setJsonFieldErrors((currentErrors) => {
                                                const nextErrors = { ...currentErrors };
                                                delete nextErrors[parameterKey];

                                                return nextErrors;
                                            });

                                            updatePayload(parameterKey, parsedJson);
                                        } catch {
                                            setJsonFieldErrors((currentErrors) => ({
                                                ...currentErrors,
                                                [parameterKey]: 'Invalid JSON value.',
                                            }));
                                        }
                                    }}
                                />
                                {jsonFieldError ? <span className="automation-dag-field-error">{jsonFieldError}</span> : null}
                            </label>
                        );
                    }

                    return (
                        <label key={parameterKey} className="automation-dag-field">
                            <span>{parameterLabel}</span>
                            <input
                                type="text"
                                value={typeof currentPayloadValue === 'string' ? currentPayloadValue : String(currentPayloadValue ?? '')}
                                onChange={(event) => updatePayload(parameterKey, event.target.value)}
                            />
                        </label>
                    );
                })}
            </div>

            <label className="automation-dag-field">
                <span>Payload Preview</span>
                <textarea value={payloadPreview} readOnly rows={8} />
            </label>

            {isLoadingOptions ? <div className="automation-dag-info-caption">Refreshing command options...</div> : null}
        </div>
    );
}

function GenericNodeConfigEditor({ draft, onDraftChange }) {
    const jsonText = typeof draft?.generic_json_text === 'string' ? draft.generic_json_text : '{}';

    return (
        <label className="automation-dag-field">
            <span>Not implemented yet for this node type. You can store a JSON object now.</span>
            <textarea
                rows={12}
                value={jsonText}
                onChange={(event) => {
                    onDraftChange((currentDraft) => ({
                        ...currentDraft,
                        generic_json_text: event.target.value,
                    }));
                }}
            />
        </label>
    );
}

function NodeConfigModal({ node, livewireId, onCancel, onCommit }) {
    const nodeType = typeof node?.data?.nodeType === 'string' ? node.data.nodeType : 'condition';
    const nodeLabel = typeof node?.data?.label === 'string' && node.data.label !== ''
        ? node.data.label
        : paletteLabel(nodeType);

    const [draft, setDraft] = useState(() => createDefaultConfigDraft(nodeType, node?.data?.config));
    const [validationError, setValidationError] = useState('');
    const [canCloseOnBackdrop, setCanCloseOnBackdrop] = useState(false);

    useEffect(() => {
        setDraft(createDefaultConfigDraft(nodeType, node?.data?.config));
        setValidationError('');
    }, [node?.id, node?.data?.config, nodeType]);

    useEffect(() => {
        setCanCloseOnBackdrop(false);

        const timeoutId = window.setTimeout(() => {
            setCanCloseOnBackdrop(true);
        }, 180);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [node?.id]);

    const save = useCallback(() => {
        try {
            const normalizedConfig = normalizeConfigForSave(nodeType, draft);
            onCommit(normalizedConfig);
        } catch (error) {
            setValidationError(error instanceof Error ? error.message : 'Unable to save node configuration.');
        }
    }, [draft, nodeType, onCommit]);

    const reset = useCallback(() => {
        setDraft(createDefaultConfigDraft(nodeType, null));
        setValidationError('');
    }, [nodeType]);

    const handleBackdropClick = useCallback((event) => {
        if (event.target !== event.currentTarget) {
            return;
        }

        if (!canCloseOnBackdrop) {
            return;
        }

        onCancel();
    }, [canCloseOnBackdrop, onCancel]);

    return (
        <div className="automation-dag-modal-overlay" role="presentation" onClick={handleBackdropClick}>
            <div
                className="automation-dag-modal"
                role="dialog"
                aria-modal="true"
                aria-label={`Configure ${nodeLabel}`}
                onClick={(event) => event.stopPropagation()}
            >
                <div className="automation-dag-modal-header">
                    <div>
                        <h3>Configure {nodeLabel}</h3>
                        <p>{paletteLabel(nodeType)} node</p>
                    </div>
                </div>

                <div className="automation-dag-modal-body">
                    {nodeType === 'telemetry-trigger' ? (
                        <TelemetryTriggerConfigEditor draft={draft} onDraftChange={setDraft} livewireId={livewireId} />
                    ) : null}

                    {nodeType === 'condition' ? (
                        <ConditionConfigEditor draft={draft} onDraftChange={setDraft} livewireId={livewireId} />
                    ) : null}

                    {nodeType === 'command' ? (
                        <CommandConfigEditor draft={draft} onDraftChange={setDraft} livewireId={livewireId} />
                    ) : null}

                    {nodeType !== 'telemetry-trigger' && nodeType !== 'condition' && nodeType !== 'command' ? (
                        <GenericNodeConfigEditor draft={draft} onDraftChange={setDraft} />
                    ) : null}

                    {validationError !== '' ? <div className="automation-dag-modal-error">{validationError}</div> : null}
                </div>

                <div className="automation-dag-modal-footer">
                    <button type="button" className="automation-dag-action automation-dag-action-secondary" onClick={onCancel}>
                        Cancel
                    </button>
                    <button type="button" className="automation-dag-action automation-dag-action-secondary" onClick={reset}>
                        Reset
                    </button>
                    <button type="button" className="automation-dag-action automation-dag-action-primary" onClick={save}>
                        Save
                    </button>
                </div>
            </div>
        </div>
    );
}

function DagBuilder({ initialGraph, livewireId }) {
    const [nodes, setNodes, onNodesChange] = useNodesState(initialGraph.nodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialGraph.edges);
    const [viewport, setViewport] = useState(initialGraph.viewport ?? DEFAULT_VIEWPORT);
    const [nodeSequence, setNodeSequence] = useState(initialGraph.nodes.length + 1);
    const [isSaving, setIsSaving] = useState(false);
    const [isDark, setIsDark] = useState(detectDarkMode());
    const [configuringNodeId, setConfiguringNodeId] = useState(null);

    const selectedNode = useMemo(() => nodes.find((node) => node.selected) ?? null, [nodes]);

    const hasSelection = useMemo(() => {
        return nodes.some((node) => node.selected) || edges.some((edge) => edge.selected);
    }, [edges, nodes]);

    useEffect(() => {
        if (!configuringNodeId) {
            return;
        }

        if (!nodes.some((node) => node.id === configuringNodeId)) {
            setConfiguringNodeId(null);
        }
    }, [configuringNodeId, nodes]);

    useEffect(() => {
        const observer = new MutationObserver(() => {
            setIsDark(detectDarkMode());
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        if (document.body) {
            observer.observe(document.body, {
                attributes: true,
                attributeFilter: ['class'],
            });
        }

        return () => {
            observer.disconnect();
        };
    }, []);

    useEffect(() => {
        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setConfiguringNodeId(null);
            }
        };

        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('keydown', handleEscape);
        };
    }, []);

    const onConnect = useCallback(
        (params) => {
            setEdges((currentEdges) =>
                addEdge(
                    {
                        ...params,
                        type: 'smoothstep',
                        markerEnd: { type: MarkerType.ArrowClosed },
                    },
                    currentEdges,
                ),
            );
        },
        [setEdges],
    );

    const addNode = useCallback(
        (type) => {
            setNodes((currentNodes) => {
                const identifier = `${type}-${Date.now()}-${nodeSequence}`;

                return [
                    ...currentNodes,
                    {
                        id: identifier,
                        type: 'workflowNode',
                        position: normalizePosition(null, currentNodes.length),
                        data: {
                            nodeType: type,
                            label: paletteLabel(type),
                            summary: '',
                        },
                    },
                ];
            });

            setNodeSequence((currentValue) => currentValue + 1);
        },
        [nodeSequence, setNodes],
    );

    const removeSelection = useCallback(() => {
        setNodes((currentNodes) => currentNodes.filter((node) => !node.selected));
        setEdges((currentEdges) => currentEdges.filter((edge) => !edge.selected));
    }, [setEdges, setNodes]);

    const saveGraph = useCallback(async () => {
        if (livewireId === '') {
            return;
        }

        setIsSaving(true);

        try {
            await callLivewireMethod(livewireId, 'saveGraph', buildGraphPayload(nodes, edges, viewport));
        } catch (error) {
            console.error('Unable to save workflow graph.', error);
        } finally {
            setIsSaving(false);
        }
    }, [edges, livewireId, nodes, viewport]);

    const nodeUnderConfiguration = useMemo(() => {
        if (!configuringNodeId) {
            return null;
        }

        return nodes.find((node) => node.id === configuringNodeId) ?? null;
    }, [configuringNodeId, nodes]);

    const commitNodeConfig = useCallback(
        (nextConfig) => {
            if (!nodeUnderConfiguration) {
                return;
            }

            setNodes((currentNodes) =>
                currentNodes.map((node) => {
                    if (node.id !== nodeUnderConfiguration.id) {
                        return node;
                    }

                    const nodeType = typeof node.data?.nodeType === 'string' ? node.data.nodeType : 'condition';
                    const summary = summarizeNodeConfig(nodeType, nextConfig);

                    return {
                        ...node,
                        data: {
                            ...(isPlainObject(node.data) ? node.data : {}),
                            nodeType,
                            label:
                                typeof node.data?.label === 'string' && node.data.label !== ''
                                    ? node.data.label
                                    : paletteLabel(nodeType),
                            config: nextConfig,
                            summary,
                        },
                    };
                }),
            );

            setConfiguringNodeId(null);
        },
        [nodeUnderConfiguration, setNodes],
    );

    return (
        <div className="automation-dag-builder-root">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                nodeTypes={NODE_TYPES}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                onMoveEnd={(_event, nextViewport) => setViewport(nextViewport)}
                onNodeDoubleClick={(_event, node) => setConfiguringNodeId(node.id)}
                defaultViewport={initialGraph.viewport ?? DEFAULT_VIEWPORT}
                defaultEdgeOptions={{
                    type: 'smoothstep',
                    markerEnd: { type: MarkerType.ArrowClosed },
                }}
                connectionLineType={ConnectionLineType.SmoothStep}
                fitView
                fitViewOptions={{ padding: 0.2 }}
                proOptions={{ hideAttribution: true }}
                colorMode={isDark ? 'dark' : 'light'}
            >
                <Panel className="automation-dag-panel automation-dag-panel-left" position="top-left">
                    <h3>Nodes</h3>
                    <div className="automation-dag-node-palette">
                        {NODE_PALETTE.map((nodeType) => (
                            <button
                                key={nodeType.type}
                                type="button"
                                className="automation-dag-node-button"
                                onClick={() => addNode(nodeType.type)}
                            >
                                {nodeType.label}
                            </button>
                        ))}
                    </div>
                </Panel>

                <Panel className="automation-dag-panel automation-dag-panel-right" position="top-right">
                    <button
                        type="button"
                        className="automation-dag-action automation-dag-action-primary"
                        onClick={saveGraph}
                        disabled={isSaving}
                    >
                        {isSaving ? 'Saving...' : 'Save DAG'}
                    </button>

                    <button
                        type="button"
                        className="automation-dag-action automation-dag-action-secondary"
                        onClick={() => {
                            if (selectedNode) {
                                setConfiguringNodeId(selectedNode.id);
                            }
                        }}
                        disabled={!selectedNode}
                    >
                        Configure Selected
                    </button>

                    <button
                        type="button"
                        className="automation-dag-action automation-dag-action-secondary"
                        onClick={removeSelection}
                        disabled={!hasSelection}
                    >
                        Delete Selection
                    </button>
                </Panel>

                <MiniMap
                    pannable
                    zoomable
                    className="automation-dag-minimap"
                    maskColor={isDark ? 'rgba(2, 6, 23, 0.5)' : 'rgba(15, 23, 42, 0.08)'}
                    nodeColor={(node) => nodeColorForMiniMap(node?.data?.nodeType, isDark)}
                />
                <Controls className="automation-dag-controls" />
                <Background
                    gap={20}
                    size={1}
                    color={isDark ? '#334155' : '#dbeafe'}
                    bgColor={isDark ? '#0f172a' : '#f8fafc'}
                />
            </ReactFlow>

            {nodeUnderConfiguration ? (
                <NodeConfigModal
                    node={nodeUnderConfiguration}
                    livewireId={livewireId}
                    onCancel={() => setConfiguringNodeId(null)}
                    onCommit={commitNodeConfig}
                />
            ) : null}
        </div>
    );
}

function mountDagBuilder(container) {
    if (container.dataset.mounted === '1') {
        return;
    }

    container.dataset.mounted = '1';

    const initialGraph = parseInitialGraph(container.dataset.initialGraph ?? '{}');
    const livewireId = container.dataset.livewireId ?? '';

    const root = createRoot(container);
    root.render(<DagBuilder initialGraph={initialGraph} livewireId={livewireId} />);
}

function bootstrapDagBuilders() {
    const containers = document.querySelectorAll('[data-automation-dag-builder]');
    containers.forEach((container) => mountDagBuilder(container));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapDagBuilders);
} else {
    bootstrapDagBuilders();
}

document.addEventListener('livewire:navigated', bootstrapDagBuilders);
