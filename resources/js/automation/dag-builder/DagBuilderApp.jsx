import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
    addEdge,
    Background,
    ConnectionLineType,
    Controls,
    MarkerType,
    MiniMap,
    Panel,
    ReactFlow,
    useEdgesState,
    useNodesState,
} from '@xyflow/react';
import { DEFAULT_VIEWPORT, NODE_PALETTE } from './constants';
import { NodeConfigModal } from './NodeConfigModal';
import { WorkflowNodeCard } from './nodes/WorkflowNodeCard';
import { callLivewireMethod } from './services/livewire';
import { summarizeNodeConfig } from './logic/config';
import { buildGraphPayload } from './logic/graph';
import {
    detectDarkMode,
    isPlainObject,
    nodeColorForMiniMap,
    normalizePosition,
    paletteLabel,
} from './logic/helpers';

const NODE_TYPES = {
    workflowNode: WorkflowNodeCard,
};

export function DagBuilderApp({ initialGraph, livewireId }) {
    const [nodes, setNodes, onNodesChange] = useNodesState(initialGraph.nodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialGraph.edges);
    const [viewport, setViewport] = useState(initialGraph.viewport ?? DEFAULT_VIEWPORT);
    const [nodeSequence, setNodeSequence] = useState(initialGraph.nodes.length + 1);
    const [isSaving, setIsSaving] = useState(false);
    const [isDark, setIsDark] = useState(detectDarkMode());
    const [configuringNodeId, setConfiguringNodeId] = useState(null);

    const selectedNode = useMemo(() => nodes.find((node) => node.selected) ?? null, [nodes]);

    const hasSelection = useMemo(() => {
        return nodes.some((node) => node.selected) || edges.some((edge) => edge.selected);
    }, [edges, nodes]);

    useEffect(() => {
        if (!configuringNodeId) {
            return;
        }

        if (!nodes.some((node) => node.id === configuringNodeId)) {
            setConfiguringNodeId(null);
        }
    }, [configuringNodeId, nodes]);

    useEffect(() => {
        const observer = new MutationObserver(() => {
            setIsDark(detectDarkMode());
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        if (document.body) {
            observer.observe(document.body, {
                attributes: true,
                attributeFilter: ['class'],
            });
        }

        return () => {
            observer.disconnect();
        };
    }, []);

    useEffect(() => {
        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setConfiguringNodeId(null);
            }
        };

        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('keydown', handleEscape);
        };
    }, []);

    const onConnect = useCallback(
        (params) => {
            setEdges((currentEdges) =>
                addEdge(
                    {
                        ...params,
                        type: 'smoothstep',
                        markerEnd: { type: MarkerType.ArrowClosed },
                    },
                    currentEdges,
                ),
            );
        },
        [setEdges],
    );

    const addNode = useCallback(
        (type) => {
            setNodes((currentNodes) => {
                const identifier = `${type}-${Date.now()}-${nodeSequence}`;

                return [
                    ...currentNodes,
                    {
                        id: identifier,
                        type: 'workflowNode',
                        position: normalizePosition(null, currentNodes.length),
                        data: {
                            nodeType: type,
                            label: paletteLabel(type),
                            summary: '',
                        },
                    },
                ];
            });

            setNodeSequence((currentValue) => currentValue + 1);
        },
        [nodeSequence, setNodes],
    );

    const removeSelection = useCallback(() => {
        setNodes((currentNodes) => currentNodes.filter((node) => !node.selected));
        setEdges((currentEdges) => currentEdges.filter((edge) => !edge.selected));
    }, [setEdges, setNodes]);

    const saveGraph = useCallback(async () => {
        if (livewireId === '') {
            return;
        }

        setIsSaving(true);

        try {
            await callLivewireMethod(livewireId, 'saveGraph', buildGraphPayload(nodes, edges, viewport));
        } catch (error) {
            console.error('Unable to save workflow graph.', error);
        } finally {
            setIsSaving(false);
        }
    }, [edges, livewireId, nodes, viewport]);

    const nodeUnderConfiguration = useMemo(() => {
        if (!configuringNodeId) {
            return null;
        }

        return nodes.find((node) => node.id === configuringNodeId) ?? null;
    }, [configuringNodeId, nodes]);

    const commitNodeConfig = useCallback(
        (nextConfig) => {
            if (!nodeUnderConfiguration) {
                return;
            }

            setNodes((currentNodes) =>
                currentNodes.map((node) => {
                    if (node.id !== nodeUnderConfiguration.id) {
                        return node;
                    }

                    const nodeType = typeof node.data?.nodeType === 'string' ? node.data.nodeType : 'condition';
                    const summary = summarizeNodeConfig(nodeType, nextConfig);

                    return {
                        ...node,
                        data: {
                            ...(isPlainObject(node.data) ? node.data : {}),
                            nodeType,
                            label:
                                typeof node.data?.label === 'string' && node.data.label !== ''
                                    ? node.data.label
                                    : paletteLabel(nodeType),
                            config: nextConfig,
                            summary,
                        },
                    };
                }),
            );

            setConfiguringNodeId(null);
        },
        [nodeUnderConfiguration, setNodes],
    );

    return (
        <div className="automation-dag-builder-root">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                nodeTypes={NODE_TYPES}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                onMoveEnd={(_event, nextViewport) => setViewport(nextViewport)}
                onNodeDoubleClick={(_event, node) => setConfiguringNodeId(node.id)}
                defaultViewport={initialGraph.viewport ?? DEFAULT_VIEWPORT}
                defaultEdgeOptions={{
                    type: 'smoothstep',
                    markerEnd: { type: MarkerType.ArrowClosed },
                }}
                connectionLineType={ConnectionLineType.SmoothStep}
                fitView
                fitViewOptions={{ padding: 0.2 }}
                proOptions={{ hideAttribution: true }}
                colorMode={isDark ? 'dark' : 'light'}
            >
                <Panel className="automation-dag-panel automation-dag-panel-left" position="top-left">
                    <h3>Nodes</h3>
                    <div className="automation-dag-node-palette">
                        {NODE_PALETTE.map((nodeType) => (
                            <button
                                key={nodeType.type}
                                type="button"
                                className="automation-dag-node-button"
                                onClick={() => addNode(nodeType.type)}
                            >
                                {nodeType.label}
                            </button>
                        ))}
                    </div>
                </Panel>

                <Panel className="automation-dag-panel automation-dag-panel-right" position="top-right">
                    <button
                        type="button"
                        className="automation-dag-action automation-dag-action-primary"
                        onClick={saveGraph}
                        disabled={isSaving}
                    >
                        {isSaving ? 'Saving...' : 'Save DAG'}
                    </button>

                    <button
                        type="button"
                        className="automation-dag-action automation-dag-action-secondary"
                        onClick={() => {
                            if (selectedNode) {
                                setConfiguringNodeId(selectedNode.id);
                            }
                        }}
                        disabled={!selectedNode}
                    >
                        Configure Selected
                    </button>

                    <button
                        type="button"
                        className="automation-dag-action automation-dag-action-secondary"
                        onClick={removeSelection}
                        disabled={!hasSelection}
                    >
                        Delete Selection
                    </button>
                </Panel>

                <MiniMap
                    pannable
                    zoomable
                    className="automation-dag-minimap"
                    maskColor={isDark ? 'rgba(2, 6, 23, 0.5)' : 'rgba(15, 23, 42, 0.08)'}
                    nodeColor={(node) => nodeColorForMiniMap(node?.data?.nodeType, isDark)}
                />
                <Controls className="automation-dag-controls" />
                <Background
                    gap={20}
                    size={1}
                    color={isDark ? '#334155' : '#dbeafe'}
                    bgColor={isDark ? '#0f172a' : '#f8fafc'}
                />
            </ReactFlow>

            {nodeUnderConfiguration ? (
                <NodeConfigModal
                    node={nodeUnderConfiguration}
                    livewireId={livewireId}
                    onCancel={() => setConfiguringNodeId(null)}
                    onCommit={commitNodeConfig}
                />
            ) : null}
        </div>
    );
}
