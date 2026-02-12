# Automation Module - Architecture

## Architectural Model

The module is split into two major planes:

1. Design-time plane: build and save workflow graphs.
2. Runtime plane: react to telemetry and execute runs.

```mermaid
graph LR
    subgraph "Design-Time Plane"
        A[Filament Resource]
        B[EditAutomationDag Page]
        C[ReactFlow DAG Builder]
        D[WorkflowGraphValidator]
        E[WorkflowNodeConfigValidator]
        F[WorkflowTelemetryTriggerCompiler]
    end

    subgraph "Runtime Plane"
        G[TelemetryReceived Event]
        H[QueueTelemetryAutomationRuns]
        I[DatabaseTriggerMatcher]
        J[StartAutomationRunFromTelemetry Job]
        K[WorkflowRunExecutor]
        L[DeviceCommandDispatcher]
    end

    subgraph "Storage"
        M[automation_workflows]
        N[automation_workflow_versions]
        O[automation_telemetry_triggers]
        P[automation_runs]
        Q[automation_run_steps]
    end

    A --> B --> C
    C --> D --> E --> N
    E --> F --> O
    N --> M

    G --> H --> I --> J --> K --> L
    J --> P
    K --> Q
```

## Component Responsibilities

| Component | Layer | Responsibility |
|-----------|------|----------------|
| `AutomationWorkflowResource` | Filament | Workflow CRUD and navigation entry point |
| `EditAutomationDag` | Livewire page | Exposes graph save API and option lookup methods for node modals |
| `dag-builder.jsx` | Frontend | ReactFlow canvas, node editing, config modal UX, graph serialization |
| `WorkflowGraphValidator` | Domain service | Validates graph topology (node ids, trigger existence, no cycles) |
| `WorkflowNodeConfigValidator` | Domain service | Validates node-specific configs against organization/device/topic/schema constraints |
| `WorkflowTelemetryTriggerCompiler` | Domain service | Compiles telemetry-trigger nodes into indexed DB rows |
| `QueueTelemetryAutomationRuns` | Event listener | Converts telemetry events into queue jobs with correlation context |
| `DatabaseTriggerMatcher` | Domain service | Matches incoming telemetry against compiled trigger rows |
| `StartAutomationRunFromTelemetry` | Queue job | Creates run record and invokes execution engine |
| `WorkflowRunExecutor` | Domain service | Executes trigger/condition/command chain and records step history |
| `DeviceCommandDispatcher` | Shared device control | Publishes target command over MQTT/NATS path |

## Dependency Direction

The dependency direction is one-way:

`UI -> Validation/Compile -> Runtime Triggering -> Execution -> Device Control`

```mermaid
graph TB
    UI[Filament + ReactFlow] --> VALIDATE[Graph and Node Validation]
    VALIDATE --> COMPILE[Trigger Compilation]
    COMPILE --> MATCH[Telemetry Trigger Matching]
    MATCH --> EXECUTE[Workflow Execution]
    EXECUTE --> DISPATCH[Device Command Dispatch]
```

No runtime component depends back on the UI layer.

## Event Wiring

`AppServiceProvider` wires automation into app runtime by:

- Binding `TriggerMatcher` to `DatabaseTriggerMatcher`.
- Registering policy for `AutomationWorkflow`.
- Listening for `TelemetryReceived` and invoking `QueueTelemetryAutomationRuns`.

This makes automation event-driven by default once telemetry persistence emits `TelemetryReceived`.

## Data Model (ER View)

```mermaid
erDiagram
    AUTOMATION_WORKFLOWS ||--o{ AUTOMATION_WORKFLOW_VERSIONS : has
    AUTOMATION_WORKFLOWS ||--o{ AUTOMATION_RUNS : has
    AUTOMATION_WORKFLOW_VERSIONS ||--o{ AUTOMATION_TELEMETRY_TRIGGERS : compiles_to
    AUTOMATION_WORKFLOW_VERSIONS ||--o{ AUTOMATION_RUNS : executes_as
    AUTOMATION_RUNS ||--o{ AUTOMATION_RUN_STEPS : has

    AUTOMATION_WORKFLOWS {
      bigint id
      bigint organization_id
      string name
      string slug
      string status
      bigint active_version_id
    }

    AUTOMATION_WORKFLOW_VERSIONS {
      bigint id
      bigint automation_workflow_id
      int version
      json graph_json
      string graph_checksum
      datetime published_at
    }

    AUTOMATION_TELEMETRY_TRIGGERS {
      bigint id
      bigint organization_id
      bigint workflow_version_id
      bigint device_id
      bigint device_type_id
      bigint schema_version_topic_id
      json filter_expression
    }

    AUTOMATION_RUNS {
      bigint id
      bigint organization_id
      bigint workflow_id
      bigint workflow_version_id
      string trigger_type
      json trigger_payload
      string status
      datetime started_at
      datetime finished_at
      json error_summary
    }

    AUTOMATION_RUN_STEPS {
      bigint id
      bigint automation_run_id
      string node_id
      string node_type
      string status
      json input_snapshot
      json output_snapshot
      json error
      int duration_ms
    }
```

## Status Models

| Workflow Status | Meaning |
|-----------------|---------|
| `draft` | Editable workflow under development |
| `active` | Intended to be active/published |
| `paused` | Temporarily disabled operationally |
| `archived` | Retained but inactive |

| Run Status | Meaning |
|------------|---------|
| `queued` | Reserved state in enum (not currently persisted in this runtime path) |
| `running` | Run created and execution in progress |
| `completed` | Run reached terminal success path (may include non-passing condition branches) |
| `failed` | Run failed due to node or execution exception |
| `cancelled` | Reserved terminal state |

## Architectural Notes

- Topology and node configuration are validated before persistence.
- Trigger matching is based on compiled rows, not full graph scans.
- Execution currently uses direct node-type handling (`condition`, `command`) in `WorkflowRunExecutor`.
- `NodeExecutor` contract exists as a future extension seam for pluggable node executors.
