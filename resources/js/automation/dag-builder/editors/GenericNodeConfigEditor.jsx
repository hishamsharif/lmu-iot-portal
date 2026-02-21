import React from 'react';
import { CodeEditorField } from '../components/CodeEditorField';

export function GenericNodeConfigEditor({ draft, onDraftChange }) {
    const jsonText = typeof draft?.generic_json_text === 'string' ? draft.generic_json_text : '{}';

    return (
        <CodeEditorField
            label="Not implemented yet for this node type. You can store a JSON object now."
            value={jsonText}
            rows={12}
            onChange={(nextValue) => {
                onDraftChange((currentDraft) => ({
                    ...currentDraft,
                    generic_json_text: nextValue,
                }));
            }}
        />
    );
}
