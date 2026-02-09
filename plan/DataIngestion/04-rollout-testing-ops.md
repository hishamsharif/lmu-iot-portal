# Rollout, Testing, and Operations

## Rollout Plan
```mermaid
flowchart LR
    A[Deploy migrations + code] --> B[Enable pipeline flag]
    B --> C[Run Horizon + ingestion listener]
    C --> D[Monitor ingest + stage metrics]
    D --> E[Tune queue and subject scope]
    E --> F[Harden JetStream/KV + analytics consumers]
    F --> G[Future driver migration path]
```

## Runtime Processes
- Horizon workers process queue jobs (`ingestion` queue).
- `iot:ingest-telemetry` is the long-running subscriber that feeds the queue.

## Operational Decision Tree
```mermaid
flowchart TD
    A[Telemetry visible in live viewer?] -->|No| B[Check simulator/device publish topic]
    A -->|Yes| C[Rows in device_telemetry_logs increasing?]

    C -->|No| D[Check queue connection alignment]
    D --> E[Horizon connection vs ingestion.queue_connection]
    E --> F[Check pending jobs and failed jobs]

    C -->|Yes| G[processing_state = processed?]
    G -->|No publish_failed| H[Check NATS KV / analytics broker path]
    G -->|Yes| I[Pipeline healthy]
```

## Minimal Runbook
1. Confirm processes:
- `php artisan horizon:status`
- `php artisan iot:ingest-telemetry`
2. Confirm queue alignment:
- `ingestion.queue_connection` matches Horizon worker connection.
3. Confirm persistence:
- `device_telemetry_logs` row count increases during simulation.
4. Confirm stage visibility:
- `ingestion_messages` and `ingestion_stage_logs` record each message lifecycle.
5. Inspect publish failures:
- Telemetry Viewer health panel + `publish_failed` rows.

## Test Coverage (Current)
- `tests/Feature/DataIngestion/TelemetryIngestionServiceTest.php`
- full valid pipeline
- validation halt
- inactive halt
- dedupe
- feature toggle kill switch
- post-persist publish failure behavior
- `tests/Unit/DataIngestion/TelemetryDerivationServiceTest.php`
- dependency-order derivation
- unresolved dependency handling
- `tests/Unit/IoT/IngestTelemetryCommandSubjectFilterTest.php`
- NATS internal/system subject filtering

## Safety Guarantees
- Telemetry persistence happens before publish side-effects.
- Publish-side failure does not lose telemetry row.
- Failure reason is captured for UI and runbook debugging.
