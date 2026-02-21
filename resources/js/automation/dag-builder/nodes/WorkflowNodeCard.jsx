import React from 'react';
import { Handle, Position } from '@xyflow/react';
import { paletteLabel } from '../logic/helpers';

export function WorkflowNodeCard({ data, selected }) {
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
