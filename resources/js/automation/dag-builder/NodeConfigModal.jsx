import React, { useCallback, useEffect, useState } from 'react';
import { createDefaultConfigDraft, normalizeConfigForSave } from './logic/config';
import { paletteLabel } from './logic/helpers';
import { TelemetryTriggerConfigEditor } from './editors/TelemetryTriggerConfigEditor';
import { ConditionConfigEditor } from './editors/ConditionConfigEditor';
import { QueryConfigEditor } from './editors/QueryConfigEditor';
import { CommandConfigEditor } from './editors/CommandConfigEditor';
import { AlertConfigEditor } from './editors/AlertConfigEditor';
import { GenericNodeConfigEditor } from './editors/GenericNodeConfigEditor';

export function NodeConfigModal({ node, livewireId, onCancel, onCommit }) {
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

                    {nodeType === 'query' ? (
                        <QueryConfigEditor draft={draft} onDraftChange={setDraft} livewireId={livewireId} />
                    ) : null}

                    {nodeType === 'command' ? (
                        <CommandConfigEditor draft={draft} onDraftChange={setDraft} livewireId={livewireId} />
                    ) : null}

                    {nodeType === 'alert' ? (
                        <AlertConfigEditor draft={draft} onDraftChange={setDraft} livewireId={livewireId} />
                    ) : null}

                    {nodeType !== 'telemetry-trigger' && nodeType !== 'condition' && nodeType !== 'query' && nodeType !== 'command' && nodeType !== 'alert' ? (
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
