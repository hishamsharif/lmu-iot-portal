import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { WINDOW_UNITS, QUERY_ALIAS_REGEX } from '../constants';
import { CodeEditorField } from '../components/CodeEditorField';
import { callLivewireMethod } from '../services/livewire';
import {
    createDefaultQuerySource,
    isPlainObject,
    normalizeWindowUnit,
    resolveQuerySourcesDraft,
    toPositiveInteger,
} from '../logic/helpers';

export function QueryConfigEditor({ draft, onDraftChange, livewireId }) {
    const windowConfig = isPlainObject(draft?.window) ? draft.window : {};
    const sources = useMemo(() => resolveQuerySourcesDraft(draft?.sources), [draft?.sources]);
    const sql = typeof draft?.sql === 'string'
        ? draft.sql
        : 'SELECT AVG(source_1.value) AS value FROM source_1';

    const [templates, setTemplates] = useState({
        default_window: {
            size: 15,
            unit: 'minute',
        },
        window_units: WINDOW_UNITS,
        runtime_tokens: [],
        sql_snippets: [
            { label: 'Average over source', sql: 'SELECT AVG(source_1.value) AS value FROM source_1' },
        ],
    });
    const [sourceOptions, setSourceOptions] = useState({});
    const [isLoadingSourceOptions, setIsLoadingSourceOptions] = useState(false);

    useEffect(() => {
        let ignore = false;

        const loadTemplates = async () => {
            try {
                const response = await callLivewireMethod(livewireId, 'getQueryNodeTemplates');

                if (ignore || !isPlainObject(response)) {
                    return;
                }

                setTemplates((currentTemplates) => ({
                    ...currentTemplates,
                    ...response,
                    window_units: Array.isArray(response.window_units) && response.window_units.length > 0
                        ? response.window_units
                        : currentTemplates.window_units,
                    runtime_tokens: Array.isArray(response.runtime_tokens) ? response.runtime_tokens : currentTemplates.runtime_tokens,
                    sql_snippets: Array.isArray(response.sql_snippets) && response.sql_snippets.length > 0
                        ? response.sql_snippets
                        : currentTemplates.sql_snippets,
                }));
            } catch (error) {
                if (!ignore) {
                    console.error('Unable to load query node templates.', error);
                }
            }
        };

        loadTemplates();

        return () => {
            ignore = true;
        };
    }, [livewireId]);

    useEffect(() => {
        let ignore = false;

        const loadSourceOptions = async () => {
            setIsLoadingSourceOptions(true);

            try {
                const responses = await Promise.all(
                    sources.map(async (source) => {
                        try {
                            const response = await callLivewireMethod(livewireId, 'getQueryNodeOptions', {
                                device_id: toPositiveInteger(source?.device_id),
                                topic_id: toPositiveInteger(source?.topic_id),
                            });

                            return isPlainObject(response) ? response : {};
                        } catch {
                            return {};
                        }
                    }),
                );

                if (ignore) {
                    return;
                }

                const nextSourceOptions = {};

                responses.forEach((response, index) => {
                    nextSourceOptions[index] = {
                        devices: Array.isArray(response.devices) ? response.devices : [],
                        topics: Array.isArray(response.topics) ? response.topics : [],
                        parameters: Array.isArray(response.parameters) ? response.parameters : [],
                    };
                });

                setSourceOptions(nextSourceOptions);
            } finally {
                if (!ignore) {
                    setIsLoadingSourceOptions(false);
                }
            }
        };

        loadSourceOptions();

        return () => {
            ignore = true;
        };
    }, [livewireId, sources]);

    const updateQueryDraft = useCallback((updater) => {
        onDraftChange((currentDraft) => {
            const currentWindow = isPlainObject(currentDraft?.window) ? currentDraft.window : {};
            const currentSources = resolveQuerySourcesDraft(currentDraft?.sources);
            const nextBase = {
                mode: 'sql',
                window: {
                    size: toPositiveInteger(currentWindow.size) ?? 15,
                    unit: normalizeWindowUnit(currentWindow.unit),
                },
                sources: currentSources,
                sql: typeof currentDraft?.sql === 'string'
                    ? currentDraft.sql
                    : 'SELECT AVG(source_1.value) AS value FROM source_1',
            };

            const nextValues = updater(nextBase);

            return {
                ...currentDraft,
                ...nextBase,
                ...nextValues,
            };
        });
    }, [onDraftChange]);

    const sourceTokens = useMemo(() => {
        const tokens = [];
        const seenTokens = {};

        sources.forEach((source) => {
            const alias = typeof source?.alias === 'string' ? source.alias.trim().toLowerCase() : '';

            if (!QUERY_ALIAS_REGEX.test(alias)) {
                return;
            }

            [
                { label: `${alias}`, token: alias },
                { label: `${alias}.value`, token: `${alias}.value` },
                { label: `${alias}.raw_value`, token: `${alias}.raw_value` },
                { label: `${alias}.recorded_at`, token: `${alias}.recorded_at` },
            ].forEach((token) => {
                if (seenTokens[token.token]) {
                    return;
                }

                seenTokens[token.token] = true;
                tokens.push(token);
            });
        });

        return tokens;
    }, [sources]);

    const mergedSqlSnippets = useMemo(() => {
        const snippetList = Array.isArray(templates.sql_snippets) ? templates.sql_snippets : [];

        return snippetList.map((snippet) => ({
            label: typeof snippet?.label === 'string' ? snippet.label : 'SQL Snippet',
            sql: typeof snippet?.sql === 'string' ? snippet.sql : '',
        }));
    }, [templates.sql_snippets]);

    const windowUnits = Array.isArray(templates.window_units) && templates.window_units.length > 0
        ? templates.window_units
        : WINDOW_UNITS;

    return (
        <div className="automation-dag-modal-grid">
            <div className="automation-dag-grid-two">
                <label className="automation-dag-field">
                    <span>Window Size</span>
                    <input
                        type="number"
                        min="1"
                        value={String(toPositiveInteger(windowConfig.size) ?? 15)}
                        onChange={(event) => {
                            const nextSize = toPositiveInteger(event.target.value) ?? 1;

                            updateQueryDraft((current) => ({
                                ...current,
                                window: {
                                    ...current.window,
                                    size: nextSize,
                                },
                            }));
                        }}
                    />
                </label>

                <label className="automation-dag-field">
                    <span>Window Unit</span>
                    <select
                        value={normalizeWindowUnit(windowConfig.unit)}
                        onChange={(event) => {
                            const nextUnit = normalizeWindowUnit(event.target.value);

                            updateQueryDraft((current) => ({
                                ...current,
                                window: {
                                    ...current.window,
                                    unit: nextUnit,
                                },
                            }));
                        }}
                    >
                        {windowUnits.map((unit) => (
                            <option key={String(unit.value)} value={String(unit.value)}>
                                {String(unit.label ?? unit.value)}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <div className="automation-dag-source-list">
                {sources.map((source, index) => {
                    const options = isPlainObject(sourceOptions[index]) ? sourceOptions[index] : { devices: [], topics: [], parameters: [] };
                    const selectedDeviceId = toPositiveInteger(source?.device_id);
                    const selectedTopicId = toPositiveInteger(source?.topic_id);
                    const selectedParameterDefinitionId = toPositiveInteger(source?.parameter_definition_id);
                    const aliasError = typeof source?.alias === 'string' && source.alias.trim() !== '' && !QUERY_ALIAS_REGEX.test(source.alias.trim())
                        ? 'Use letters, numbers, and underscore only.'
                        : '';

                    return (
                        <div key={`query-source-${index}`} className="automation-dag-source-row">
                            <div className="automation-dag-source-row-header">
                                <strong>Source {index + 1}</strong>

                                <button
                                    type="button"
                                    className="automation-dag-action automation-dag-action-secondary"
                                    onClick={() => {
                                        updateQueryDraft((current) => {
                                            const nextSources = current.sources.filter((_, sourceIndex) => sourceIndex !== index);

                                            return {
                                                ...current,
                                                sources: nextSources.length > 0 ? nextSources : [createDefaultQuerySource(0)],
                                            };
                                        });
                                    }}
                                    disabled={sources.length === 1}
                                >
                                    Remove
                                </button>
                            </div>

                            <div className="automation-dag-grid-two">
                                <label className="automation-dag-field">
                                    <span>Alias</span>
                                    <input
                                        type="text"
                                        value={typeof source?.alias === 'string' ? source.alias : ''}
                                        onChange={(event) => {
                                            const nextAlias = event.target.value;

                                            updateQueryDraft((current) => ({
                                                ...current,
                                                sources: current.sources.map((currentSource, sourceIndex) => (
                                                    sourceIndex === index
                                                        ? {
                                                              ...currentSource,
                                                              alias: nextAlias,
                                                          }
                                                        : currentSource
                                                )),
                                            }));
                                        }}
                                    />
                                    {aliasError !== '' ? <span className="automation-dag-field-error">{aliasError}</span> : null}
                                </label>

                                <label className="automation-dag-field">
                                    <span>Device</span>
                                    <select
                                        value={selectedDeviceId ?? ''}
                                        onChange={(event) => {
                                            const nextDeviceId = toPositiveInteger(event.target.value);

                                            updateQueryDraft((current) => ({
                                                ...current,
                                                sources: current.sources.map((currentSource, sourceIndex) => (
                                                    sourceIndex === index
                                                        ? {
                                                              ...currentSource,
                                                              device_id: nextDeviceId,
                                                              topic_id: null,
                                                              parameter_definition_id: null,
                                                          }
                                                        : currentSource
                                                )),
                                            }));
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
                                    <span>Topic</span>
                                    <select
                                        value={selectedTopicId ?? ''}
                                        onChange={(event) => {
                                            const nextTopicId = toPositiveInteger(event.target.value);

                                            updateQueryDraft((current) => ({
                                                ...current,
                                                sources: current.sources.map((currentSource, sourceIndex) => (
                                                    sourceIndex === index
                                                        ? {
                                                              ...currentSource,
                                                              topic_id: nextTopicId,
                                                              parameter_definition_id: null,
                                                          }
                                                        : currentSource
                                                )),
                                            }));
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
                                    <span>Parameter</span>
                                    <select
                                        value={selectedParameterDefinitionId ?? ''}
                                        onChange={(event) => {
                                            const nextParameterDefinitionId = toPositiveInteger(event.target.value);

                                            updateQueryDraft((current) => ({
                                                ...current,
                                                sources: current.sources.map((currentSource, sourceIndex) => (
                                                    sourceIndex === index
                                                        ? {
                                                              ...currentSource,
                                                              parameter_definition_id: nextParameterDefinitionId,
                                                          }
                                                        : currentSource
                                                )),
                                            }));
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
                            </div>
                        </div>
                    );
                })}
            </div>

            <button
                type="button"
                className="automation-dag-action automation-dag-action-secondary"
                onClick={() => {
                    updateQueryDraft((current) => ({
                        ...current,
                        sources: [...current.sources, createDefaultQuerySource(current.sources.length)],
                    }));
                }}
            >
                Add Source
            </button>

            <CodeEditorField
                label="SQL Query"
                value={sql}
                rows={12}
                placeholder="SELECT AVG(source_1.value) AS value FROM source_1"
                snippets={mergedSqlSnippets}
                tokens={sourceTokens}
                onChange={(nextSql) => {
                    updateQueryDraft((current) => ({
                        ...current,
                        sql: nextSql,
                    }));
                }}
            />

            <div className="automation-dag-info-panel">
                <strong>Query Contract</strong>
                <div>Your SQL must return one row and include a numeric <code>value</code> column.</div>
                <div>Each source alias is exposed as a CTE table with <code>value</code>, <code>raw_value</code>, and <code>recorded_at</code>.</div>
                {isLoadingSourceOptions ? <div className="automation-dag-info-caption">Refreshing source options...</div> : null}
            </div>
        </div>
    );
}
