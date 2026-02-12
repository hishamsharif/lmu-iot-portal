# Telemetry Module - Architecture

## Architectural Model

Telemetry is split into two planes:

1. Ingress plane: NATS subject listener and queueing.
2. Processing plane: deterministic stage pipeline and persistence.

```mermaid
graph LR
    subgraph "Ingress Plane"
        A[iot:ingest-telemetry]
        B[IncomingTelemetryEnvelope]
        C[ProcessInboundTelemetryJob]
    end

    subgraph "Processing Plane"
        D[TelemetryIngestionService]
        E[DeviceTelemetryTopicResolver]
        F[TelemetryValidationService]
        G[TelemetryMutationService]
        H[TelemetryDerivationService]
        I[TelemetryPersistenceService]
        J[TelemetryAnalyticsPublishService]
        K[HotStateStore]
        L[AnalyticsPublisher]
    end

    subgraph "Integration Plane"
        M[TelemetryIncoming]
        N[TelemetryReceived]
        O[Telemetry Viewer]
        P[Automation QueueTelemetryAutomationRuns]
        Q[DevicePresenceService]
    end

    subgraph "Storage"
        R[ingestion_messages]
        S[ingestion_stage_logs]
        T[device_telemetry_logs]
    end

    A --> B --> C --> D
    A --> M

    D --> E
    D --> F --> G --> H --> I --> J

    D --> R
    D --> S
    I --> T

    I --> Q
    I --> N
    M --> O
    N --> O
    N --> P

    J --> K
    J --> L
```

## Component Responsibilities

| Component | Layer | Responsibility |
|-----------|------|----------------|
| `IngestTelemetryCommand` | Ingress | Subscribes to NATS subject, filters internal/analytics subjects, builds envelope, dispatches queue job |
| `IncomingTelemetryEnvelope` | DTO | Transport-neutral telemetry envelope + dedupe key generation |
| `ProcessInboundTelemetryJob` | Queue | Runs pipeline on configured queue/connection |
| `TelemetryIngestionService` | Orchestrator | Runs ordered stages, updates ingestion status, writes stage logs |
| `DeviceTelemetryTopicResolver` | Resolver | Maps MQTT topic to concrete `Device` + publish `SchemaVersionTopic` |
| `TelemetryValidationService` | Stage service | Extracts parameter values and evaluates validation rules |
| `TelemetryMutationService` | Stage service | Applies per-parameter mutation expressions |
| `TelemetryDerivationService` | Stage service | Computes derived parameters from dependency graph |
| `TelemetryPersistenceService` | Stage service | Persists `DeviceTelemetryLog`, marks presence online, fires `TelemetryReceived` |
| `TelemetryAnalyticsPublishService` | Stage service | Publishes analytics/invalid events via abstraction |
| `NatsKvHotStateStore` | Infra | Stores latest telemetry state in NATS KV |
| `NatsAnalyticsPublisher` | Infra | Publishes analytics/invalid payloads to NATS subjects |

## Dependency Direction

Telemetry follows one-way dependencies:

`Ingress -> Queue -> Orchestrator -> Stage Services -> Persistence/Publishers -> Events`

```mermaid
graph TB
    INGRESS[Ingress command] --> QUEUE[Queue job]
    QUEUE --> ORCH[TelemetryIngestionService]
    ORCH --> STAGES[Validation/Mutation/Derivation]
    STAGES --> PERSIST[TelemetryPersistenceService]
    PERSIST --> SIDEFX[Hot state + analytics]
    PERSIST --> EVENTS[TelemetryReceived]
```

No lower layer depends on UI components.

## Service Container and Feature Gates

| Mechanism | Current Use |
|-----------|-------------|
| Container bindings | `HotStateStore` -> `NatsKvHotStateStore`, `AnalyticsPublisher` -> `NatsAnalyticsPublisher` |
| Feature flags (`FeatureServiceProvider`) | `ingestion.pipeline.enabled`, `ingestion.pipeline.driver`, `ingestion.pipeline.publish_analytics` |
| Config gates | `ingestion.publish_invalid_events`, `ingestion.capture_stage_snapshots` |

## Data Model (ER View)

```mermaid
erDiagram
    INGESTION_MESSAGES ||--o{ INGESTION_STAGE_LOGS : has
    INGESTION_MESSAGES ||--o| DEVICE_TELEMETRY_LOGS : creates

    DEVICES ||--o{ DEVICE_TELEMETRY_LOGS : emits
    DEVICE_SCHEMA_VERSIONS ||--o{ DEVICE_TELEMETRY_LOGS : schema_context
    SCHEMA_VERSION_TOPICS ||--o{ DEVICE_TELEMETRY_LOGS : topic_context

    SCHEMA_VERSION_TOPICS ||--o{ PARAMETER_DEFINITIONS : defines
    DEVICE_SCHEMA_VERSIONS ||--o{ DERIVED_PARAMETER_DEFINITIONS : defines

    INGESTION_MESSAGES {
      uuid id
      bigint organization_id
      bigint device_id
      bigint device_schema_version_id
      bigint schema_version_topic_id
      string source_subject
      string source_message_id
      string source_deduplication_key
      json raw_payload
      json error_summary
      string status
      datetime received_at
      datetime processed_at
    }

    INGESTION_STAGE_LOGS {
      bigint id
      uuid ingestion_message_id
      string stage
      string status
      int duration_ms
      json input_snapshot
      json output_snapshot
      json change_set
      json errors
      datetime created_at
    }

    DEVICE_TELEMETRY_LOGS {
      uuid id
      bigint device_id
      bigint device_schema_version_id
      bigint schema_version_topic_id
      uuid ingestion_message_id
      string validation_status
      string processing_state
      json raw_payload
      json validation_errors
      json mutated_values
      json transformed_values
      datetime recorded_at
      datetime received_at
    }
```

## Storage Notes

| Area | Detail |
|------|--------|
| Telemetry table shape | `device_telemetry_logs` uses UUID id and `recorded_at` in composite primary key |
| Time-series optimization | If PostgreSQL is used, migration enables TimescaleDB hypertable on `recorded_at` |
| Query indexes | Status, recorded time, and ingestion foreign key indexes are present |
| Stage-level observability | Every stage writes `ingestion_stage_logs` row with duration and optional snapshots |
