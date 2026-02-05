# Phase 1 — Database Schema First (Laravel + Postgres)

## Goal
Lock a normalized, multi-tenant (Organization-scoped) relational schema in Postgres for:
- Device onboarding and configuration (device types, schemas, schema versions).
- Telemetry shape definition (parameters, derived parameters, error codes).
- Device identity and credentials (for MQTT now; extensible to HTTP later).
- Fast “current values” UI support (latest readings snapshot table).

This schema must support **either** ingestion implemented:
- **Solely in Laravel** (long-running MQTT consumer + workers), **or**
- In a **separate service** (e.g., Go) that reads Postgres config and writes to a time-series store.

## Non-goals (Phase 1)
- Dashboards, widgets, rule engine, alerts, and command logs (planned in Phase 2+).
- Time-series database schema (Timescale hypertables, aggregates).
- MQTT broker deployment, ACL enforcement, or device firmware details.

## Design principles (decision-complete)
1. **Tenant boundary**: everything attaches to `organizations.id`.
2. **Versioned schema contract**:
   - Schema changes are done by creating a **new** version.
   - “Active” versions should be treated as immutable (enforced in app logic).
3. **Relational, normalized**: child tables reference parents; avoid redundant data.
4. **Stable device identity**: `devices.uuid` is the stable public identifier for MQTT topics and provisioning.
5. **Fast “current values” UI**: store latest values in `device_latest_readings` (avoid time-series queries for live widgets).

## Key terminology
- **DeviceType**: category of device (e.g., energy meter 3-phase, LED actuator).
- **DeviceSchema**: contract blueprint for a device type (payload format, high-level definition).
- **DeviceSchemaVersion**: versioned contract containing concrete parameter definitions.
- **ParameterDefinition**: incoming telemetry key definition (type/unit/validation/JSON-path).
- **DerivedParameterDefinition**: computed parameter definition (expression + dependencies).
- **DeviceErrorCode**: mapping of device-reported error codes to severity/message.
- **Device**: an instance (physical or simulated) pinned to a schema version.
- **DeviceCredential**: broker credentials / auth data for a device.
- **DeviceLatestReading**: last known values snapshot for UI.

## Open decisions (tracked here)
### Ingestion implementation
- **Option A (Laravel-only)**: MQTT consumer + batch writes + Horizon jobs.
- **Option B (Go service)**: dedicated ingestion service reading Postgres configs.

Phase 1 schema supports both options; decision is gated as **DR-1** (Phase 3).

### Supported protocols (config only in Phase 1)
- Default target: **MQTT**.
- Future option: HTTP ingestion.

## Deliverables
- `plan/01-erd-core.mmd`: core ERD for Phase 1 tables.
- `plan/02-erd-extension.mmd`: extension ERD for Phase 2+ (dashboards/rules).
- `plan/03-backlog.md`: GitHub Projects-ready user stories and sub-tasks.
- `plan/04-report-outline.md`: report outline and evidence checklist.

