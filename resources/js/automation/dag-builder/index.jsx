import React from 'react';
import { createRoot } from 'react-dom/client';
import { DagBuilderApp } from './DagBuilderApp';
import { parseInitialGraph } from './logic/graph';

function mountDagBuilder(container) {
    if (container.dataset.mounted === '1') {
        return;
    }

    container.dataset.mounted = '1';

    const initialGraph = parseInitialGraph(container.dataset.initialGraph ?? '{}');
    const livewireId = container.dataset.livewireId ?? '';

    const root = createRoot(container);
    root.render(<DagBuilderApp initialGraph={initialGraph} livewireId={livewireId} />);
}

export function bootstrapDagBuilders() {
    const containers = document.querySelectorAll('[data-automation-dag-builder]');
    containers.forEach((container) => mountDagBuilder(container));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapDagBuilders);
} else {
    bootstrapDagBuilders();
}

document.addEventListener('livewire:navigated', bootstrapDagBuilders);
