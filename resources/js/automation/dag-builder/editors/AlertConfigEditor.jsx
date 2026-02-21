import React, { useEffect, useState } from 'react';
import { WINDOW_UNITS } from '../constants';
import { CodeEditorField } from '../components/CodeEditorField';
import { callLivewireMethod } from '../services/livewire';
import {
    isPlainObject,
    isValidEmail,
    normalizeWindowUnit,
    parseEmailRecipientInput,
    toPositiveInteger,
} from '../logic/helpers';

export function AlertConfigEditor({ draft, onDraftChange, livewireId }) {
    const [runtimeTokens, setRuntimeTokens] = useState([
        { label: 'Trigger Value', token: '{{ trigger.value }}' },
        { label: 'Query Value', token: '{{ query.value }}' },
        { label: 'Run ID', token: '{{ run.id }}' },
        { label: 'Workflow ID', token: '{{ run.workflow_id }}' },
    ]);

    useEffect(() => {
        let ignore = false;

        const loadTemplates = async () => {
            try {
                const response = await callLivewireMethod(livewireId, 'getQueryNodeTemplates');

                if (ignore || !isPlainObject(response)) {
                    return;
                }

                if (Array.isArray(response.runtime_tokens) && response.runtime_tokens.length > 0) {
                    setRuntimeTokens(response.runtime_tokens);
                }
            } catch (error) {
                if (!ignore) {
                    console.error('Unable to load alert template tokens.', error);
                }
            }
        };

        loadTemplates();

        return () => {
            ignore = true;
        };
    }, [livewireId]);

    const recipientsText = typeof draft?.recipients_text === 'string'
        ? draft.recipients_text
        : Array.isArray(draft?.recipients)
            ? draft.recipients.join('\n')
            : '';
    const subject = typeof draft?.subject === 'string' ? draft.subject : '';
    const body = typeof draft?.body === 'string' ? draft.body : '';
    const cooldown = isPlainObject(draft?.cooldown) ? draft.cooldown : {};
    const cooldownValue = toPositiveInteger(cooldown.value) ?? 30;
    const cooldownUnit = normalizeWindowUnit(cooldown.unit);
    const recipientList = parseEmailRecipientInput(recipientsText);
    const invalidRecipients = recipientList.filter((recipient) => !isValidEmail(recipient));

    return (
        <div className="automation-dag-modal-grid">
            <div className="automation-dag-grid-two">
                <label className="automation-dag-field">
                    <span>Channel</span>
                    <select value="email" disabled>
                        <option value="email">Email</option>
                    </select>
                </label>

                <label className="automation-dag-field">
                    <span>Cooldown</span>
                    <div className="automation-dag-field-inline-group">
                        <input
                            type="number"
                            min="1"
                            value={String(cooldownValue)}
                            onChange={(event) => {
                                const nextValue = toPositiveInteger(event.target.value) ?? 1;

                                onDraftChange((currentDraft) => ({
                                    ...currentDraft,
                                    channel: 'email',
                                    cooldown: {
                                        value: nextValue,
                                        unit: normalizeWindowUnit(currentDraft?.cooldown?.unit),
                                    },
                                }));
                            }}
                        />
                        <select
                            value={cooldownUnit}
                            onChange={(event) => {
                                const nextUnit = normalizeWindowUnit(event.target.value);

                                onDraftChange((currentDraft) => ({
                                    ...currentDraft,
                                    channel: 'email',
                                    cooldown: {
                                        value: toPositiveInteger(currentDraft?.cooldown?.value) ?? 30,
                                        unit: nextUnit,
                                    },
                                }));
                            }}
                        >
                            {WINDOW_UNITS.map((unit) => (
                                <option key={String(unit.value)} value={String(unit.value)}>
                                    {String(unit.label ?? unit.value)}
                                </option>
                            ))}
                        </select>
                    </div>
                </label>
            </div>

            <label className="automation-dag-field">
                <span>Recipients (one email per line)</span>
                <textarea
                    rows={4}
                    value={recipientsText}
                    onChange={(event) => {
                        const nextText = event.target.value;
                        const nextRecipients = parseEmailRecipientInput(nextText);

                        onDraftChange((currentDraft) => ({
                            ...currentDraft,
                            channel: 'email',
                            recipients_text: nextText,
                            recipients: nextRecipients,
                        }));
                    }}
                />
                {invalidRecipients.length > 0 ? (
                    <span className="automation-dag-field-error">
                        Invalid email(s): {invalidRecipients.join(', ')}
                    </span>
                ) : null}
            </label>

            <label className="automation-dag-field">
                <span>Subject</span>
                <input
                    type="text"
                    value={subject}
                    onChange={(event) => {
                        onDraftChange((currentDraft) => ({
                            ...currentDraft,
                            channel: 'email',
                            subject: event.target.value,
                        }));
                    }}
                />
            </label>

            <CodeEditorField
                label="Body"
                value={body}
                rows={10}
                tokens={runtimeTokens}
                onChange={(nextBody) => {
                    onDraftChange((currentDraft) => ({
                        ...currentDraft,
                        channel: 'email',
                        body: nextBody,
                    }));
                }}
            />
        </div>
    );
}
