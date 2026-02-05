# Phase 1 Core ERD

```mermaid
erDiagram
  ORGANIZATIONS {
    bigint id PK
    uuid uuid UK
    varchar name
    varchar slug UK
    varchar logo
    timestamptz created_at
    timestamptz updated_at
    timestamptz deleted_at
  }

  USERS {
    bigint id PK
    varchar name
    varchar email UK
    boolean is_super_admin
    timestamptz created_at
    timestamptz updated_at
  }

  ORGANIZATION_USER {
    bigint id PK
    bigint organization_id FK
    bigint user_id FK
    timestamptz created_at
    timestamptz updated_at
  }

  DEVICE_TYPES {
    bigint id PK
    bigint organization_id FK
    varchar key
    varchar name
    varchar default_protocol
    jsonb protocol_config
    timestamptz created_at
    timestamptz updated_at
    timestamptz deleted_at
  }

  DEVICE_SCHEMAS {
    bigint id PK
    bigint device_type_id FK
    varchar name
    timestamptz created_at
    timestamptz updated_at
    timestamptz deleted_at
  }

  DEVICE_SCHEMA_VERSIONS {
    bigint id PK
    bigint device_schema_id FK
    int version
    varchar status
    text notes
    timestamptz created_at
    timestamptz updated_at
  }

  PARAMETER_DEFINITIONS {
    bigint id PK
    bigint device_schema_version_id FK
    varchar key
    varchar label
    varchar data_type
    varchar unit
    boolean required
    varchar json_path
    jsonb validation
    jsonb display
    timestamptz created_at
    timestamptz updated_at
  }

  DERIVED_PARAMETER_DEFINITIONS {
    bigint id PK
    bigint device_schema_version_id FK
    varchar key
    varchar label
    varchar data_type
    varchar unit
    text expression
    jsonb depends_on
    timestamptz created_at
    timestamptz updated_at
  }

  DEVICE_ERROR_CODES {
    bigint id PK
    bigint device_schema_version_id FK
    varchar code
    varchar severity
    text message_template
    jsonb details_schema
    timestamptz created_at
    timestamptz updated_at
  }

  DEVICES {
    bigint id PK
    bigint organization_id FK
    bigint device_type_id FK
    bigint device_schema_version_id FK
    uuid uuid UK
    varchar name
    varchar external_id
    boolean is_simulated
    varchar connection_state
    timestamptz last_seen_at
    timestamptz created_at
    timestamptz updated_at
    timestamptz deleted_at
  }

  DEVICE_CREDENTIALS {
    bigint id PK
    bigint device_id FK
    varchar mqtt_username
    varchar mqtt_password_hash
    varchar mqtt_client_id
    jsonb acl
    timestamptz rotated_at
    timestamptz created_at
    timestamptz updated_at
  }

  DEVICE_LATEST_READINGS {
    bigint id PK
    bigint device_id FK
    bigint device_schema_version_id FK
    timestamptz captured_at
    jsonb values
    jsonb quality
    timestamptz created_at
    timestamptz updated_at
  }

  DEVICE_COMMAND_DEFINITIONS {
    bigint id PK
    bigint device_schema_version_id FK
    varchar key
    varchar label
    varchar protocol
    varchar topic_template
    jsonb payload_schema
    timestamptz created_at
    timestamptz updated_at
  }

  DEVICE_DESIRED_STATES {
    bigint id PK
    bigint device_id FK
    bigint device_schema_version_id FK
    jsonb state
    timestamptz expires_at
    timestamptz created_at
    timestamptz updated_at
  }

  DEVICE_COMMAND_LOGS {
    bigint id PK
    bigint device_id FK
    bigint device_command_definition_id FK
    jsonb payload
    varchar status
    timestamptz sent_at
    timestamptz acked_at
    text error
    timestamptz created_at
    timestamptz updated_at
  }

  ORGANIZATIONS ||--o{ ORGANIZATION_USER : has
  USERS ||--o{ ORGANIZATION_USER : belongs_to

  ORGANIZATIONS ||--o{ DEVICE_TYPES : owns
  DEVICE_TYPES ||--o{ DEVICE_SCHEMAS : defines
  DEVICE_SCHEMAS ||--o{ DEVICE_SCHEMA_VERSIONS : versions
  DEVICE_SCHEMA_VERSIONS ||--o{ PARAMETER_DEFINITIONS : has
  DEVICE_SCHEMA_VERSIONS ||--o{ DERIVED_PARAMETER_DEFINITIONS : derives
  DEVICE_SCHEMA_VERSIONS ||--o{ DEVICE_ERROR_CODES : recognizes

  ORGANIZATIONS ||--o{ DEVICES : owns
  DEVICE_TYPES ||--o{ DEVICES : categorizes
  DEVICE_SCHEMA_VERSIONS ||--o{ DEVICES : pins

  DEVICES ||--o{ DEVICE_CREDENTIALS : has
  DEVICES ||--|| DEVICE_LATEST_READINGS : latest
  DEVICE_SCHEMA_VERSIONS ||--o{ DEVICE_COMMAND_DEFINITIONS : supports
  DEVICES ||--|| DEVICE_DESIRED_STATES : targets
  DEVICES ||--o{ DEVICE_COMMAND_LOGS : sends
  DEVICE_COMMAND_DEFINITIONS ||--o{ DEVICE_COMMAND_LOGS : executes
```

## Architectural Notes

### Device Types: Global Catalog + Org Overrides
- `device_types.organization_id` is **nullable**.
- Global types (organization_id = NULL) are shared across all orgs (e.g., standard energy meters, LED actuators).
- Org-specific types (organization_id set) allow orgs to define custom device types.
- Uniqueness: `key` must be unique for global types; `(organization_id, key)` must be unique for org-specific types.

### Protocol Config: Type-Safe Class Architecture
Instead of storing untyped JSON, `protocol_config` is serialized/deserialized to protocol-specific classes:
- **Interface**: `App\Domain\IoT\Contracts\ProtocolConfigInterface` defines the contract.
- **MQTT**: `App\Domain\IoT\ProtocolConfigs\MqttProtocolConfig` with broker settings, topic templates, QoS, etc.
- **HTTP**: `App\Domain\IoT\ProtocolConfigs\HttpProtocolConfig` with endpoints, method, headers, auth type, etc.
- **Cast**: `App\Domain\IoT\Casts\ProtocolConfigCast` deserializes based on `default_protocol` value.
- **Enum**: `App\Domain\IoT\Enums\Protocol` (Mqtt, Http).

Topic templates use placeholders (e.g., `device/:device_uuid/data`, `device/:device_uuid/ctrl`) replaced at runtime.

### Device Instance Scoping
- `devices` table is **always** org-scoped (organization_id NOT NULL).
- Each device pins to a specific `device_schema_version_id` at registration (immutable contract).
- `devices.uuid` is the stable public identifier used in MQTT topics and API calls.

### Command & Control
- **Command definitions**: per schema version, define allowed commands with payload schemas.
- **Desired state**: per device, stores target state for reconciliation.
- **Command logs**: audit trail of sent commands with status and acknowledgment timestamps.

