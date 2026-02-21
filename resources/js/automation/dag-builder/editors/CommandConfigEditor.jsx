import React, { useCallback, useEffect, useState } from 'react';
import { CodeEditorField } from '../components/CodeEditorField';
import { callLivewireMethod } from '../services/livewire';
import {
    isPlainObject,
    safeJsonStringify,
    toPositiveInteger,
} from '../logic/helpers';

export function CommandConfigEditor({ draft, onDraftChange, livewireId }) {
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
                        const resolvedColor = typeof currentPayloadValue === 'string' && /^#[0-9A-Fa-f]{6}$/.test(currentPayloadValue)
                            ? currentPayloadValue.toUpperCase()
                            : '#FF0000';

                        return (
                            <label key={parameterKey} className="automation-dag-field">
                                <span>{parameterLabel}</span>
                                <div className="automation-dag-color-control">
                                    <input
                                        type="color"
                                        className="automation-dag-color-input"
                                        value={resolvedColor}
                                        onChange={(event) => updatePayload(parameterKey, event.target.value.toUpperCase())}
                                    />
                                    <span className="automation-dag-color-value">{resolvedColor}</span>
                                </div>
                            </label>
                        );
                    }

                    if (widget === 'json' || parameterType === 'json') {
                        const jsonDraft = typeof jsonFieldDrafts[parameterKey] === 'string'
                            ? jsonFieldDrafts[parameterKey]
                            : safeJsonStringify(currentPayloadValue ?? {});
                        const jsonFieldError = jsonFieldErrors[parameterKey];

                        return (
                            <CodeEditorField
                                key={parameterKey}
                                label={parameterLabel}
                                value={jsonDraft}
                                rows={4}
                                error={typeof jsonFieldError === 'string' ? jsonFieldError : ''}
                                onChange={(nextDraft) => {
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

            <CodeEditorField
                label="Payload Preview"
                value={payloadPreview}
                rows={8}
                readOnly
                onChange={() => {}}
            />

            {isLoadingOptions ? <div className="automation-dag-info-caption">Refreshing command options...</div> : null}
        </div>
    );
}
