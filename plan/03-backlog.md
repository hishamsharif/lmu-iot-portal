# Backlog (GitHub Projects-ready)

## Project board suggestion
Columns:
- Backlog
- Ready
- In Progress
- In Review
- Done

Labels (recommended):
- `area:db`, `area:filament`, `area:ingestion`, `area:sim`, `area:report`
- `type:story`, `type:task`
- `prio:P0`, `prio:P1`, `prio:P2`

Milestones (phases):
1) Phase 1 — Core Schema + Admin UI (DB + Filament Resources)  
2) Phase 2 — Advanced Admin Features (Schema Editors, Provisioning, Bulk Actions)  
3) Phase 3 — Telemetry Ingestion (Laravel vs Go decision)  
4) Phase 4 — Dashboards & Visualization  
5) Phase 5 — Rules & Device Control  
6) Phase 6 — Simulation & Evaluation  
7) Phase 7 — Project Report

---

## Phase 1 — Core Schema + Admin UI (P0)

Strategy: Build complete vertical slices—each story delivers migration + model + factory/seeder + Filament resource + tests. This allows immediate validation of the data model through the UI.

### US-1: Device Type management (data model + protocol config architecture)
Story: As an org admin, I can define device types so devices can be categorized with type-safe protocol configurations.

**Terminology**:
- `key`: Machine-readable identifier (e.g., `energy_meter_3phase`, `led_actuator_rgb`). Used in code/APIs. Must be kebab-case, alphanumeric + underscore/dash only.
- `name`: Human-readable label (e.g., "3-Phase Energy Meter", "RGB LED Actuator"). Used in UI.
- `default_protocol`: Enum value (`mqtt` or `http`). Determines which protocol config class to use.
- `protocol_config`: JSON column storing serialized protocol-specific configuration objects.

**Protocol Config Architecture**:
- Abstract interface: `App\Domain\IoT\Contracts\ProtocolConfigInterface`
  - Methods: `validate(): bool`, `getTelemetryTopicTemplate(): string`, `getControlTopicTemplate(): ?string`, `toArray(): array`
- MQTT implementation: `App\Domain\IoT\ProtocolConfigs\MqttProtocolConfig`
  - Properties: `broker_host`, `broker_port`, `username`, `password`, `use_tls`, `telemetry_topic_template` (default: `device/:device_uuid/data`), `control_topic_template` (default: `device/:device_uuid/ctrl`), `qos` (default: 1), `retain` (default: false)
- HTTP implementation: `App\Domain\IoT\ProtocolConfigs\HttpProtocolConfig`
  - Properties: `base_url`, `telemetry_endpoint`, `control_endpoint`, `method`, `headers` (array), `auth_type` (enum: none/basic/bearer), `timeout` (default: 30)
- Custom Eloquent cast: `App\Domain\IoT\Casts\ProtocolConfigCast` to serialize/deserialize based on `default_protocol` value

Acceptance:
- Store `key` (unique per org or globally), `name`, `default_protocol`, `protocol_config`.
- Support global catalog entries (organization_id = null) with optional org overrides.
- Enforce unique `key` for global types and unique `(organization_id, key)` for org-specific types.
- Protocol config must be validated against the corresponding protocol class schema.
- Protocol classes must be immutable value objects (readonly properties or no setters).

Sub-tasks:
1. **Protocol config foundation**:
   - Interface: `App\Domain\IoT\Contracts\ProtocolConfigInterface`
   - MQTT config: `App\Domain\IoT\ProtocolConfigs\MqttProtocolConfig` (constructor property promotion, implements interface)
   - HTTP config: `App\Domain\IoT\ProtocolConfigs\HttpProtocolConfig`
   - Eloquent cast: `App\Domain\IoT\Casts\ProtocolConfigCast` (deserializes JSON to correct class based on protocol)
   - Enum: `App\Domain\IoT\Enums\Protocol` (cases: Mqtt, Http)
2. **Database & model**:
   - Migration: `device_types` (with `key`, `name`, `default_protocol` as varchar, `protocol_config` as jsonb)
   - Model: `App\Domain\IoT\Models\DeviceType` with `organization()` relation, casts for `default_protocol` → `Protocol` enum and `protocol_config` → `ProtocolConfigCast`
3. **Seeders & factories**:
   - Factory: create global and org-specific device types with valid protocol configs
   - Seeder: 2 global types (energy_meter_3phase with MQTT, led_actuator_rgb with MQTT), 1 org-specific override
4. **Filament resource**:
   - Resource: `DeviceTypeResource` (Admin panel) with form supporting protocol selection and dynamic config fields
   - Form: protocol select (live) → show MQTT or HTTP fields based on selection
   - Table: key, name, protocol badge, org scope indicator
5. **Tests**:
   - Pest: CRUD operations, org scoping enforcement, global vs org-specific uniqueness
   - Pest: protocol config serialization/deserialization (MQTT and HTTP)
   - Pest: validation failures for invalid protocol configs

### US-2: Device schema versions with parameter definitions
Story: As an org admin, I can define versioned device schemas with parameter definitions so telemetry structure and validation rules are consistently enforced.

**Terminology**:
- **Device Schema**: A contract blueprint for a device type (e.g., "Energy Meter V1 Contract").
- **Schema Version**: Versioned instance of a schema containing concrete parameter definitions.
- **Parameter Definition**: Incoming telemetry key definition with type, unit, validation rules, and JSON path for extraction.

**Schema Versioning**:
- Schemas belong to a device type.
- Versions are ordered integers; unique `(device_schema_id, version)`.
- Only one "active" version per schema (enforced in app logic).
- Versioning allows contract evolution without breaking existing devices.

**Parameter Validation**:
- Each parameter has a `json_path` (e.g., `$.voltage.L1`) to extract values from telemetry payload.
- Validation rules stored as JSON (e.g., `{"min": 0, "max": 500, "type": "numeric"}`).
- Display config stored as JSON (e.g., `{"decimals": 2, "unit_position": "suffix"}`).

Acceptance:
- Store device schemas with name.
- Schema versions with integer version numbers and status.
- Parameter definitions with key, label, data_type, unit, required flag, json_path, validation rules, display config.
- Enforce unique `(device_schema_id, version)` and unique `(device_schema_version_id, key)` for parameters.
- Only one "active" version per schema.

Sub-tasks:
1. **Migrations**:
   - Migration: `device_schemas` (id, device_type_id, name, timestamps, soft delete)
   - Migration: `device_schema_versions` (id, device_schema_id, version, status, notes, timestamps)
   - Migration: `parameter_definitions` (id, device_schema_version_id, key, label, data_type, unit, required, json_path, validation, display, timestamps)
   - Unique constraints and indexes
2. **Models & relationships**:
   - Model: `DeviceSchema` with `deviceType()` and `versions()` relationships
   - Model: `DeviceSchemaVersion` with `schema()` and `parameters()` relationships
   - Model: `ParameterDefinition` with `schemaVersion()` relationship
   - Proper casts for JSON fields (validation, display)
3. **Seeders & factories**:
   - Factory: `DeviceSchemaFactory` and `DeviceSchemaVersionFactory`
   - Factory: `ParameterDefinitionFactory` with realistic validation rules
   - Seeder: 2 schema versions (energy meter v1 with 7 parameters, LED actuator v1 with 3 parameters)
   - Sample parameters: V1, V2, V3, I1, I2, I3, E for energy meter
4. **Filament resource**:
   - Resource: `DeviceSchemaResource` with schema CRUD
   - Relation manager: version listing on schema detail page
   - Relation manager: parameter definitions on schema version detail page (with inline creation/editing)
   - Form: parameter repeater with json_path builder, validation rule builder
   - Table: version status badges, parameter count
5. **Tests**:
   - Pest: schema creation, version ordering, unique constraints
   - Pest: parameter creation with json_path and validation rules
   - Pest: active version enforcement (one active per schema)
   - Pest: parameter JSON path parsing and validation rule application

### US-3: Derived parameters
Story: As an org admin, I can define derived parameters so the platform can compute additional metrics.

Acceptance:
- Derived definitions reference schema version.
- Store a safe expression and dependencies.

Sub-tasks:
- Migration: `derived_parameter_definitions`
- Model: `DerivedParameterDefinition`
- Seed: sample derived parameters (e.g., power factor, total energy)
- Filament relation manager: add to schema version view
- Pest tests: expression validation, dependency tracking

### US-4: Error code catalog
Story: As an org admin, I can map device error codes so ingestion/UI can interpret faults.

Acceptance:
- Unique `(device_schema_version_id, code)`.

Sub-tasks:
- Migration: `device_error_codes`
- Model: `DeviceErrorCode`
- Seed: sample error codes for energy meter
- Filament relation manager: add to schema version view
- Pest tests: unique code constraint, severity enum

### US-5: Device registration & identity
Story: As an org admin, I can register devices and pin them to a schema version.

Acceptance:
- Device UUID is stable and unique per org: unique `(organization_id, uuid)`.
- Track `connection_state` + `last_seen_at`.

Sub-tasks:
- Migration: `devices`
- Model: `Device` with `organization()`, `deviceType()`, `schemaVersion()` relations
- Factory + seeder: create 100 simulated devices (metadata only)
- Filament resource: `DeviceResource` with filters (type, status), search, bulk actions
- Pest tests: device registration, UUID uniqueness, tenant isolation

### US-6: Provisioning credentials
Story: As an org admin, I can create MQTT credentials so devices can authenticate to the broker.

Acceptance:
- Credentials tied to a device; support rotation.

Sub-tasks:
- Migration: `device_credentials`
- Model: `DeviceCredential`
- Filament relation manager: view/regenerate credentials (on device edit page)
- Pest tests: credential generation, rotation, password hashing

### US-7: Latest readings snapshot table
Story: As a portal user, I can see the latest readings without querying time-series storage.

Acceptance:
- One row per device: unique `(device_id)`.
- Includes schema version used to parse it.

Sub-tasks:
- Migration: `device_latest_readings`
- Model: `DeviceLatestReading`
- Seed: populate with random telemetry for all 100 devices
- Filament infolist: display on device view page (read-only latest values)
- Pest tests: upsert logic, one row per device constraint

### US-8: Device control definitions, state, and logs
Story: As a portal user, I can send control commands and track desired state for devices.

Acceptance:
- Define allowed commands per schema version with a payload schema and MQTT topic template.
- Store a desired state per device for eventual reconciliation.
- Record command logs with status, timestamps, and errors.

Sub-tasks:
- Migrations: `device_command_definitions`, `device_desired_states`, `device_command_logs`
- Models: `DeviceCommandDefinition`, `DeviceDesiredState`, `DeviceCommandLog`
- Seed: command definitions for LED actuator (on/off/blink)
- Filament relation managers: commands on schema version, command log on device page
- Pest tests: command schema validation, desired state reconciliation, log tracking

---

## Phase 2 — Advanced Admin Features (P1)

Focus: Enhanced UI/UX for complex workflows that weren't essential for basic CRUD.

### US-9: Schema version editor with parameter builder
Story: As an org admin, I can create/edit schema versions with a rich parameter definition editor.

Acceptance:
- Inline parameter/derived parameter/error code editors within schema version form.
- Visual validation rule builder (min/max, regex, enum).
- JSON-path auto-suggest based on payload format.

Sub-tasks:
- Custom Filament form with repeater for parameters
- JSON schema validation preview
- Pest tests: complex schema creation, validation

### US-10: Device provisioning workflow
Story: As an org admin, I can provision devices with a guided wizard.

Acceptance:
- Multi-step wizard: select type → select schema version → configure metadata → generate credentials.
- Auto-generate MQTT credentials with QR code export.
- Bulk import from CSV.

Sub-tasks:
- Filament wizard component
- Credential generation action
- QR code export
- Pest tests: wizard flow, bulk import

### US-11: Bulk actions and data management
Story: As an org admin, I can perform bulk operations on devices.

Acceptance:
- Bulk assign schema version.
- Bulk regenerate credentials.
- Export device list with credentials (CSV).

Sub-tasks:
- Filament bulk actions
- CSV export with credentials
- Pest tests: bulk operations, export format

---

## Phase 3 — Ingestion Decision (P0 gate)
### DR-1: Laravel-only vs Go ingestion
Inputs:
- Telemetry cadence: ~1 message/min/device
- Devices: ~100 simulated + 1 prototype
- Requirements: validation, derive, rules, alerts, control

Output:
- Decide ingestion implementation path while keeping Phase 1 DB schema unchanged.
