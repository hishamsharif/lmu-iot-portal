# Data Ingestion Architecture

## Purpose
This domain ingests device telemetry from NATS/MQTT subjects, validates/mutates/derives values, persists to the Timescale-backed telemetry table, and publishes downstream analytics/state updates.

## End-to-End Flow
```mermaid
flowchart TB
    A["Device / Simulator"] -->|Publish telemetry| B["NATS Subject"]
    B --> C["iot:ingest-telemetry"]
    C -->|Filter + resolve known topic| D[("Queue: ingestion")]
    D --> E["ProcessInboundTelemetryJob"]
    E --> F["TelemetryIngestionService"]

    F --> G["Ingress + Dedup"]
    G --> H["Validate"]

    H -->|Invalid| I["Persist invalid telemetry (processing_state=invalid)"]
    H -->|Valid + inactive device| J["Persist inactive (processing_state=inactive_skipped)"]
    H -->|Valid + active| K["Mutate"]

    K --> L["Derive"]
    L --> M["Persist final telemetry (processing_state=processed)"]

    I --> Q[("device_telemetry_logs")]
    J --> Q
    M --> Q

    M --> N["Hot state publish (NATS KV)"]
    M --> O["Analytics publish (NATS subject)"]

    N -->|Failure| P["Mark publish_failed + log reason"]
    O -->|Failure| P

    P --> R[("ingestion_messages + ingestion_stage_logs")]
```

## Sequence (Runtime)
```mermaid
sequenceDiagram
    participant Device
    participant NATS
    participant Cmd as iot:ingest-telemetry
    participant Queue
    participant Job
    participant Svc as TelemetryIngestionService
    participant TSDB as device_telemetry_logs

    Device->>NATS: telemetry payload
    NATS->>Cmd: subject message
    Cmd->>Cmd: ignore internal/system subjects
    Cmd->>Cmd: resolve topic -> device/schema
    Cmd->>Queue: dispatch ProcessInboundTelemetryJob
    Queue->>Job: run
    Job->>Svc: ingest(envelope)

    Svc->>Svc: ingress + dedupe + stage log
    Svc->>Svc: validate payload

    alt Validation fails
        Svc->>TSDB: persist invalid row
        Svc->>Svc: message=status failed_validation
    else Device inactive
        Svc->>TSDB: persist inactive_skipped row
        Svc->>Svc: message=status inactive_skipped
    else Active + valid
        Svc->>Svc: mutate + derive
        Svc->>TSDB: persist processed row
        Svc->>Svc: publish hot-state + analytics
        alt Publish side-effect fails
            Svc->>Svc: row processing_state=publish_failed
            Svc->>Svc: message=status failed_terminal
        else Publish succeeds
            Svc->>Svc: message=status completed
        end
    end
```

## Core Runtime Components
- Command: `app/Console/Commands/IoT/IngestTelemetryCommand.php`
- Queue job: `app/Domain/DataIngestion/Jobs/ProcessInboundTelemetryJob.php`
- Orchestrator: `app/Domain/DataIngestion/Services/TelemetryIngestionService.php`
- Stage services:
- `DeviceTelemetryTopicResolver`
- `TelemetryValidationService`
- `TelemetryMutationService`
- `TelemetryDerivationService`
- `TelemetryPersistenceService`
- `TelemetryAnalyticsPublishService`

## Stage Outcomes
- `validate` failure: persisted + halted.
- inactive device: persisted + halted.
- active valid payload: mutate -> derive -> persist -> publish.
- dedupe hit: marked `duplicate`, no downstream replay.
- publish side-effect failure: data remains persisted; state marked `publish_failed`; error reason recorded.

## Feature Flags
- `ingestion.pipeline.enabled`
- `ingestion.pipeline.driver`
- `ingestion.pipeline.publish_analytics`

## Current Safety Notes
- Internal NATS subjects (`$JS.*`, `$KV.*`, `_REQS.*`, `_INBOX.*`) are ignored by ingestion command.
- Unknown/irrelevant subjects are dropped before queue dispatch.
- JetStream/KV unavailability no longer blocks telemetry persistence.
