import React, { useCallback, useRef } from 'react';

export function CodeEditorField({
    label,
    value,
    onChange,
    rows = 10,
    placeholder = '',
    error = '',
    readOnly = false,
    tokens = [],
    snippets = [],
}) {
    const textareaRef = useRef(null);
    const resolvedValue = typeof value === 'string' ? value : '';

    const insertText = useCallback((insertable) => {
        if (readOnly || typeof insertable !== 'string' || insertable === '') {
            return;
        }

        const textarea = textareaRef.current;
        const start = typeof textarea?.selectionStart === 'number' ? textarea.selectionStart : resolvedValue.length;
        const end = typeof textarea?.selectionEnd === 'number' ? textarea.selectionEnd : resolvedValue.length;
        const nextValue = `${resolvedValue.slice(0, start)}${insertable}${resolvedValue.slice(end)}`;

        onChange(nextValue);

        window.requestAnimationFrame(() => {
            if (!textareaRef.current) {
                return;
            }

            const cursor = start + insertable.length;
            textareaRef.current.focus();
            textareaRef.current.setSelectionRange(cursor, cursor);
        });
    }, [onChange, readOnly, resolvedValue]);

    return (
        <label className="automation-dag-field">
            <span>{label}</span>

            {tokens.length > 0 || snippets.length > 0 ? (
                <div className="automation-dag-code-toolbar">
                    {snippets.map((snippet, index) => {
                        const snippetLabel = typeof snippet?.label === 'string' ? snippet.label : `Snippet ${index + 1}`;
                        const snippetValue = typeof snippet?.sql === 'string'
                            ? snippet.sql
                            : typeof snippet?.value === 'string'
                                ? snippet.value
                                : '';

                        if (snippetValue === '') {
                            return null;
                        }

                        return (
                            <button
                                key={`snippet-${snippetLabel}-${index}`}
                                type="button"
                                className="automation-dag-code-pill"
                                onClick={() => insertText(snippetValue)}
                            >
                                {snippetLabel}
                            </button>
                        );
                    })}

                    {tokens.map((token, index) => {
                        const tokenLabel = typeof token?.label === 'string' ? token.label : `Token ${index + 1}`;
                        const tokenValue = typeof token?.token === 'string'
                            ? token.token
                            : typeof token?.value === 'string'
                                ? token.value
                                : '';

                        if (tokenValue === '') {
                            return null;
                        }

                        return (
                            <button
                                key={`token-${tokenLabel}-${index}`}
                                type="button"
                                className="automation-dag-code-pill"
                                onClick={() => insertText(tokenValue)}
                            >
                                {tokenLabel}
                            </button>
                        );
                    })}
                </div>
            ) : null}

            <textarea
                ref={textareaRef}
                rows={rows}
                className="automation-dag-code-editor"
                value={resolvedValue}
                placeholder={placeholder}
                readOnly={readOnly}
                onChange={(event) => onChange(event.target.value)}
            />

            {error !== '' ? <span className="automation-dag-field-error">{error}</span> : null}
        </label>
    );
}
