import { MarkerType } from '@xyflow/react';
import { DEFAULT_VIEWPORT, NODE_PALETTE } from '../constants.js';
import {
    isPlainObject,
    normalizePosition,
    paletteLabel,
} from './helpers.js';
import { summarizeNodeConfig } from './config.js';

export function parseInitialGraph(rawGraph) {
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

export function normalizeNode(node, index) {
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

export function normalizeEdge(edge, index) {
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

export function buildGraphPayload(nodes, edges, viewport) {
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
