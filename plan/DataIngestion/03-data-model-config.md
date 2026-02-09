# Data Model and Configuration

## Data Model Overview
```mermaid
erDiagram
    ORGANIZATIONS ||--o| ORGANIZATION_INGESTION_PROFILES : has
    ORGANIZATIONS ||--o{ INGESTION_MESSAGES : owns

    DEVICES ||--o{ INGESTION_MESSAGES : references
    DEVICE_SCHEMA_VERSIONS ||--o{ INGESTION_MESSAGES : references
    SCHEMA_VERSION_TOPICS ||--o{ INGESTION_MESSAGES : references

    INGESTION_MESSAGES ||--o{ INGESTION_STAGE_LOGS : records
    INGESTION_MESSAGES ||--o| DEVICE_TELEMETRY_LOGS : links

    DEVICES ||--o{ DEVICE_TELEMETRY_LOGS : emits
    DEVICE_SCHEMA_VERSIONS ||--o{ DEVICE_TELEMETRY_LOGS : validates_with
    SCHEMA_VERSION_TOPICS ||--o{ DEVICE_TELEMETRY_LOGS : from_topic

    INGESTION_MESSAGES {
      uuid id PK
      string source_subject
      string source_protocol
      string source_message_id
      string source_deduplication_key UK
      jsonb raw_payload
      jsonb error_summary
      string status
      timestamp received_at
      timestamp processed_at
    }

    INGESTION_STAGE_LOGS {
      bigint id PK
      uuid ingestion_message_id FK
      string stage
      string status
      int duration_ms
      jsonb input_snapshot
      jsonb output_snapshot
      jsonb change_set
      jsonb errors
      timestamp created_at
    }

    DEVICE_TELEMETRY_LOGS {
      uuid id PK
      uuid ingestion_message_id FK
      jsonb raw_payload
      jsonb validation_errors
      jsonb mutated_values
      jsonb transformed_values
      string validation_status
      string processing_state
      timestamp recorded_at
      timestamp received_at
    }
```

## State Semantics
- `ingestion_messages.status`
- `queued`, `processing`, `completed`, `failed_validation`, `inactive_skipped`, `failed_terminal`, `duplicate`.
- `device_telemetry_logs.processing_state`
- `processed`, `invalid`, `inactive_skipped`, `publish_failed`.

## Config Resolution
```mermaid
flowchart TD
    A[.env] --> B[config/ingestion.php]
    B --> C[iot:ingest-telemetry runtime options]
    C --> D[Job dispatch + queue connection]
    B --> E[Service behavior toggles]
    E --> F[Validation snapshots / publish flags / subject prefixes]
```

## Key Configuration Values
- `ingestion.enabled`
- `ingestion.driver`
- `ingestion.queue_connection`
- `ingestion.queue`
- `ingestion.nats.host`
- `ingestion.nats.port`
- `ingestion.nats.subject`
- `ingestion.nats.analytics_subject_prefix`
- `ingestion.nats.invalid_subject_prefix`

## Schema Extension Points
- `device_schema_versions.ingestion_config`: per-schema rules and strategy knobs.
- `devices.ingestion_overrides`: per-device overrides for ingestion behavior.
- `organization_ingestion_profiles`: retention/soft quota policy envelope.

## Query Tips for Debugging
- Latest failures by stage: `ingestion_stage_logs where status='failed_terminal'`.
- Latest publish-side issues: `device_telemetry_logs where processing_state='publish_failed'`.
- End-to-end trace: join `device_telemetry_logs.ingestion_message_id` -> `ingestion_messages` -> `ingestion_stage_logs`.
