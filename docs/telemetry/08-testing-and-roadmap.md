# Telemetry Module - Testing and Roadmap

## Current Automated Coverage

### Feature tests

| Test File | Coverage Focus |
|-----------|----------------|
| `tests/Feature/DataIngestion/TelemetryIngestionServiceTest.php` | End-to-end ingestion outcomes: valid path, validation failure, inactive device, duplicate dedupe, publish failure, feature-disabled gate |
| `tests/Feature/Telemetry/TelemetryIncomingTest.php` | `TelemetryIncoming` dispatch behavior |
| `tests/Feature/Telemetry/TelemetryBroadcastTest.php` | `TelemetryReceived` broadcast on recording |
| `tests/Feature/DeviceTelemetryLogTest.php` | Telemetry persistence and transformed payload behavior |
| `tests/Filament/Admin/Pages/TelemetryViewerTest.php` | Telemetry viewer page rendering and query-string robustness |

### Unit tests

| Test File | Coverage Focus |
|-----------|----------------|
| `tests/Unit/DataIngestion/TelemetryDerivationServiceTest.php` | Dependency-based derived parameter resolution |
| `tests/Unit/IoT/IngestTelemetryCommandSubjectFilterTest.php` | Subject filtering for internal and analytics loop prevention |

## Manual QA Checklist

1. Start listener and ingestion workers.
2. Send telemetry from simulator for a known publish topic.
3. Confirm `ingestion_messages` transitions to `completed` for valid payloads.
4. Confirm six stage logs exist on happy path.
5. Confirm `device_telemetry_logs` contains expected raw/mutated/transformed values.
6. Confirm telemetry viewer pre-ingestion panel updates in realtime.
7. Confirm telemetry viewer ingestion health panel reflects publish failures when induced.
8. Confirm automation listener reacts to `TelemetryReceived` for configured workflows.

## Known Gaps

| Area | Current State |
|------|---------------|
| Retention enforcement | Retention-related config/model exists; no active retention pruning worker yet |
| Topic registry invalidation | TTL-based refresh only; no explicit event-driven cache invalidation |
| Side effect retry policy | Publish stage marks terminal on first failure |
| Ingestion-level structured logging | Stage rows are strong; dedicated pipeline log channel is not yet a first-class telemetry channel |
| Multi-driver ingestion | Driver flag exists; practical path currently centered on `laravel` |

## Suggested Roadmap

### Phase 1: Operational hardening

1. Add retention pruning jobs for ingestion and telemetry data.
2. Add alert thresholds on `failed_terminal` and `publish_failed` rates.
3. Add replay tooling for terminal failed ingestion messages.

### Phase 2: Scale refinements

1. Split ingestion workers into dedicated Horizon supervisor profiles.
2. Introduce targeted side-effect retry/backoff and dead-letter handling.
3. Add per-organization ingestion policy application from `organization_ingestion_profiles`.

### Phase 3: Product integrations

1. Expand telemetry viewer with stage-log drill-down links by message id.
2. Add richer cross-linking from telemetry rows to automation runs triggered by the same event.
3. Add configurable payload redaction policies for sensitive telemetry fields.

## Success Criteria for Next Iteration

- Ingestion reliability remains high under sustained queue load.
- Publish side effects are retry-safe and observable.
- Retention is automated and predictable.
- Debugging from telemetry symptom to root cause is possible in minutes using first-party UI and tables.
