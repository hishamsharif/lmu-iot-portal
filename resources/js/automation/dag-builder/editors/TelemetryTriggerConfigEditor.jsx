import React, { useCallback, useEffect, useState } from 'react';
import { callLivewireMethod } from '../services/livewire';
import {
    compactJson,
    isPlainObject,
    toPositiveInteger,
} from '../logic/helpers';

export function TelemetryTriggerConfigEditor({ draft, onDraftChange, livewireId }) {
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
