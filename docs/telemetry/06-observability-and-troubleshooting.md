# Telemetry Module - Observability and Troubleshooting

## Primary Observability Surfaces

| Surface | What It Tells You |
|--------|--------------------|
| `ingestion_messages` | High-level outcome per envelope (status, error summary, processing timestamps) |
| `ingestion_stage_logs` | Stage-by-stage duration, input/output snapshots, and errors |
| `device_telemetry_logs` | Durable telemetry payloads and processing state |
| Telemetry Viewer | Live pre-ingestion stream + publish failure health panel |
| Horizon Dashboard | Queue depth, wait time, worker availability for `ingestion` queue |

## First-Response Debug Path

1. Confirm listener is running: `iot:ingest-telemetry`.
2. Confirm ingestion queue has active workers (`ingestion` queue on configured connection).
3. Inspect latest `ingestion_messages` status.
4. Inspect matching `ingestion_stage_logs` for the message.
5. Inspect `device_telemetry_logs.processing_state` for persist outcome.
6. Check telemetry viewer for pre-ingestion event visibility.

## Common Failure Modes

### 1. No telemetry records are created

Likely causes:

- Ingestion feature disabled (`ingestion.pipeline.enabled` false).
- Listener not running.
- Queue workers not consuming configured ingestion queue.
- Incoming subject filtered out as internal/analytics/invalid prefix.

Check:

- Feature/config values.
- Horizon queue assignment and wait metrics.
- Whether `TelemetryIncoming` appears in viewer stream.

### 2. Ingestion status is `failed_terminal` with `topic_not_registered`

Cause:

- Topic resolver could not map incoming MQTT topic to a device publish topic.

Check:

- Device has schema version assigned.
- Schema version has active publish topics.
- Actual incoming topic path matches resolved topic format.

### 3. Ingestion status is `failed_validation`

Cause:

- Parameter required/type/range/rule mismatch.

Check:

- `ingestion_stage_logs` at `validate` stage.
- `device_telemetry_logs.validation_errors` payload.
- Parameter definition rules for the publish topic.

### 4. Telemetry is persisted but `processing_state` is `publish_failed`

Cause:

- Post-persist side effect failed (hot-state write or analytics publish).

Check:

- `ingestion_messages.error_summary.errors.hot_state`
- `ingestion_messages.error_summary.errors.analytics_publish`
- NATS/KV availability and connectivity.

### 5. Ingestion status is `duplicate` unexpectedly

Cause:

- Same dedupe key seen again (usually same source message id).

Check:

- Upstream message id behavior.
- Dedup key expectations for replay scenarios.

### 6. Realtime viewer not updating

Cause:

- Broadcast transport issue (Reverb/Pusher config), frontend binding issue, or device/topic filter mismatch in viewer.

Check:

- Broadcast connection config.
- Browser-side channel subscription to `telemetry`.
- Selected device and topic filters in viewer.

## Stage Log Interpretation Guide

| Stage | If Missing | If Failed |
|-------|------------|-----------|
| `ingress` | Message likely not processed or duplicate short-circuit | Topic resolution or schema context issue |
| `validate` | Pipeline likely failed before validation | Payload contract mismatch |
| `mutate` | Validation failed or inactive skip path | Mutation expression/runtime issue |
| `derive` | Mutation not reached | Derived dependency/expression issue |
| `persist` | Upstream stages failed | DB persistence path issue |
| `publish` | Inactive or validation short-circuit | Side effect transport/backend issue |

## Practical Monitoring Signals

| Signal | Healthy Direction |
|--------|-------------------|
| `failed_terminal` ratio | Low and stable |
| `failed_validation` ratio | Expected for noisy inputs, but should be understood by device cohort |
| `publish_failed` telemetry rows | Near zero in steady state |
| Ingestion queue wait time | Low and predictable |
| Stage duration (`duration_ms`) | Stable within known throughput envelope |
