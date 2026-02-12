# Telemetry Module - Schema Contract, Validation, and Transformation

## Contract Model

Telemetry ingestion is schema-driven, not topic-string-driven alone.

```mermaid
graph TB
    A[Device] --> B[DeviceSchemaVersion]
    B --> C[SchemaVersionTopic direction=publish]
    C --> D[ParameterDefinition[]]
    B --> E[DerivedParameterDefinition[]]

    D --> F[Validate + Mutate]
    E --> G[Derive]
    F --> H[Final values]
    G --> H
```

## Topic Resolution

`DeviceTelemetryTopicResolver` builds an in-memory registry of resolvable publish topics:

- Loads devices with schema version + topics.
- Includes only topics where direction is publish.
- Resolves concrete MQTT topic using device identifier and topic suffix.
- Caches registry with TTL (`INGESTION_REGISTRY_TTL_SECONDS`, default 30s).

If no topic mapping is found, ingestion fails terminal with `topic_not_registered`.

## Parameter Contract (Base Values)

Each `ParameterDefinition` contributes:

| Field | Purpose |
|-------|---------|
| `json_path` | Extraction path from raw payload |
| `type` | Value type contract (integer, decimal, boolean, string, json) |
| `required` | Required value gate |
| `is_critical` | Controls warning vs invalid severity |
| `validation_rules` | Min/max/enum/regex constraints |
| `mutation_expression` | JSON logic transform from extracted value |
| `is_active` + `sequence` | Execution ordering and enabled state |

## Validation Semantics

`TelemetryValidationService` returns both extracted values and status:

- `valid`: no parameter validation failures.
- `warning`: at least one non-critical validation failure.
- `invalid`: at least one critical validation failure.

Pipeline pass/fail behavior is strict: any validation error causes pipeline short-circuit (`failed_validation`).

## Mutation Semantics

`TelemetryMutationService` applies each parameter’s `mutation_expression` (if defined) and returns:

- `mutated_values`
- `change_set` with before/after values per parameter key

Mutation occurs only after validation passes.

## Derivation Semantics

`TelemetryDerivationService` computes derived parameters from `DerivedParameterDefinition` expressions.

Behavior:

1. Uses dependency list to determine readiness.
2. Resolves in iterative passes until no additional progress.
3. Stops safely if unresolved dependency cycles or missing dependencies remain.
4. Returns `derived_values` and merged `final_values`.

## Persisted Payload Shapes

`device_telemetry_logs` stores four payload-related columns:

| Column | Meaning |
|--------|---------|
| `raw_payload` | Original inbound JSON payload |
| `validation_errors` | Parameter-keyed validation metadata |
| `mutated_values` | Post-mutation base values |
| `transformed_values` | Final values after derivation merge |

## Practical Example (Voltage Use Case)

For an energy meter payload with voltage:

1. Raw payload arrives with voltage field.
2. Voltage parameter is extracted and validated.
3. Optional calibration mutation adjusts value.
4. Optional derived parameters (for example power bands) are computed.
5. Final transformed payload is persisted and emitted to integrations.

## Notes on Config Overrides

Two persistence-level configuration surfaces exist:

| Surface | Current State |
|---------|---------------|
| `device_schema_versions.ingestion_config` | Available for schema-level ingestion settings |
| `devices.ingestion_overrides` | Available for per-device ingestion overrides |

Current pipeline behavior relies primarily on global config + feature flags; these columns are prepared for deeper policy overrides in a next phase.
