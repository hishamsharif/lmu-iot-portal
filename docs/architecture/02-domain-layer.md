# Domain Layer Architecture

## Overview

The domain layer is organized following **Domain-Driven Design (DDD)** principles, with each domain encapsulating its own models, business logic, and responsibilities.

```mermaid
graph TB
    subgraph "Shared Domain"
        U[User Model]
        O[Organization Model]
    end
    
    subgraph "Authorization Domain"
        R[Role Model]
        P[Permissions System]
    end
    
    subgraph "Device Management Domain"
        DT[DeviceType Model]
        D[Device Model]
        DL[Device Lifecycle]
    end
    
    subgraph "Device Schema Domain"
        DS[DeviceSchema Model]
        DSV[DeviceSchemaVersion Model]
        SVT[SchemaVersionTopic Model]
        PD[ParameterDefinition Model]
        DPD[DerivedParameterDefinition Model]
    end
    
    subgraph "Telemetry Domain"
        DTL[DeviceTelemetryLog Model]
        V[JsonLogicEvaluator]
    end
    
    subgraph "Device Control Domain"
        DDS[DeviceDesiredState Model]
        DCL[DeviceCommandLog Model]
    end
    
    R --> O
    DT --> O
    D --> O
    D --> DT
    D --> DSV
    DS --> DT
    DSV --> DS
    SVT --> DSV
    PD --> SVT
    DPD --> DSV
    DTL --> D
    DTL --> DSV
    DTL --> SVT
    DDS --> D
    DCL --> D
    DCL --> SVT
```

## Domain Details

### 1. Shared Domain

**Purpose**: Common models and functionality shared across all domains.

```mermaid
classDiagram
    class User {
        +id: bigint
        +name: string
        +email: string
        +is_super_admin: boolean
        +organizations()
        +roles()
    }
    
    class Organization {
        +id: bigint
        +uuid: uuid
        +name: string
        +slug: string
        +logo: string
        +users()
        +roles()
        +devices()
        +deviceTypes()
    }
    
    User "many" -- "many" Organization : belongs to
```

**Key Responsibilities**:
- User authentication and profile management
- Organization (tenant) management
- Many-to-many user-organization relationships

---

### 2. Authorization Domain

**Purpose**: Role-based access control with organization-level scoping.

```mermaid
classDiagram
    class Role {
        +id: bigint
        +organization_id: bigint
        +name: string
        +guard_name: string
        +organization()
        +permissions()
    }
    
    class Permission {
        +id: bigint
        +name: string
        +guard_name: string
    }
    
    Role "many" -- "many" Permission : has
    Organization "1" -- "many" Role : scopes
```

**Key Responsibilities**:
- Role creation and management per organization
- Permission assignment to roles
- User role assignment with organization context
- Policy enforcement via Laravel's authorization gates

**Integration**: Uses Spatie's Laravel-Permission package with custom organization scoping.

---

### 3. Device Management Domain

**Purpose**: Device catalog and instance management.

```mermaid
classDiagram
    class DeviceType {
        +id: bigint
        +organization_id: bigint?
        +key: string
        +name: string
        +default_protocol: Protocol
        +protocol_config: ProtocolConfig
        +organization()
        +schemas()
        +devices()
    }
    
    class Device {
        +id: bigint
        +organization_id: bigint
        +device_type_id: bigint
        +device_schema_version_id: bigint
        +uuid: uuid
        +name: string
        +external_id: string
        +metadata: json
        +is_active: boolean
        +is_simulated: boolean
        +connection_state: ConnectionState
        +last_seen_at: timestamp
        +organization()
        +deviceType()
        +schemaVersion()
        +telemetryLogs()
        +commandLogs()
        +desiredState()
    }
    
    DeviceType "1" -- "many" Device : categorizes
    Organization "1" -- "many" Device : owns
```

**Key Responsibilities**:
- Device type catalog (global + organization-specific)
- Device registration and provisioning
- Device lifecycle management (activation, deactivation, soft delete)
- Connection state tracking
- Protocol configuration (MQTT/HTTP)

**Protocol Configuration**: Type-safe classes implementing `ProtocolConfigInterface`:
- `MqttProtocolConfig`: Broker settings, topic templates, QoS
- `HttpProtocolConfig`: Endpoints, methods, headers, auth

---

### 4. Device Schema Domain

**Purpose**: Flexible, versioned device data contracts.

```mermaid
classDiagram
    class DeviceSchema {
        +id: bigint
        +device_type_id: bigint
        +name: string
        +deviceType()
        +versions()
    }
    
    class DeviceSchemaVersion {
        +id: bigint
        +device_schema_id: bigint
        +version: int
        +status: VersionStatus
        +notes: text
        +schema()
        +topics()
        +derivedParameters()
        +telemetryLogs()
    }
    
    class SchemaVersionTopic {
        +id: bigint
        +device_schema_version_id: bigint
        +key: string
        +label: string
        +direction: TopicDirection
        +suffix: string
        +description: text
        +qos: int
        +retain: boolean
        +sequence: int
        +schemaVersion()
        +parameters()
        +commandLogs()
    }
    
    class ParameterDefinition {
        +id: bigint
        +schema_version_topic_id: bigint
        +key: string
        +label: string
        +json_path: string
        +type: ParameterType
        +unit: string
        +required: boolean
        +is_critical: boolean
        +validation_rules: json
        +validation_error_code: string
        +mutation_expression: string
        +sequence: int
        +is_active: boolean
        +default_value: mixed
        +topic()
    }
    
    class DerivedParameterDefinition {
        +id: bigint
        +device_schema_version_id: bigint
        +key: string
        +label: string
        +data_type: ParameterType
        +unit: string
        +expression: string
        +dependencies: json
        +schemaVersion()
    }
    
    DeviceSchema "1" -- "many" DeviceSchemaVersion : versions
    DeviceSchemaVersion "1" -- "many" SchemaVersionTopic : has
    SchemaVersionTopic "1" -- "many" ParameterDefinition : defines
    DeviceSchemaVersion "1" -- "many" DerivedParameterDefinition : computes
```

**Key Responsibilities**:
- Schema definition and versioning
- Topic-based parameter organization (telemetry/command separation)
- Parameter validation rules and data types
- Derived parameter computation (expressions + dependencies)
- Schema immutability enforcement for active versions

**Versioning Strategy**:
- New versions created for schema changes
- Devices pin to specific schema version on registration
- Active versions are immutable (enforced in application logic)

---

### 5. Telemetry Domain

**Purpose**: Incoming device data validation and logging.

```mermaid
classDiagram
    class DeviceTelemetryLog {
        +id: uuid
        +device_id: bigint
        +device_schema_version_id: bigint
        +schema_version_topic_id: bigint
        +validation_status: ValidationStatus
        +raw_payload: json
        +transformed_values: json
        +recorded_at: timestamp
        +received_at: timestamp
        +device()
        +schemaVersion()
        +topic()
    }
    
    class JsonLogicEvaluator {
        +evaluate(expression, data)
        +evaluateMutation(expression, value)
    }
    
    DeviceTelemetryLog ..> JsonLogicEvaluator : uses
```

**Key Responsibilities**:
- Validate incoming telemetry against schema version
- Extract parameters using JSON paths
- Apply mutation expressions to transform values
- Log validation results (success/failure with details)
- Store raw payload + transformed values

**Validation Flow**:
1. Match incoming message to device + topic
2. Extract parameters using defined JSON paths
3. Apply mutation expressions (e.g., unit conversions)
4. Validate against parameter rules
5. Compute derived parameters
6. Store log with validation status

---

### 6. Device Control Domain

**Purpose**: Command execution and desired state management.

```mermaid
classDiagram
    class DeviceDesiredState {
        +id: bigint
        +device_id: bigint
        +desired_state: json
        +reconciled_at: timestamp
        +device()
    }
    
    class DeviceCommandLog {
        +id: bigint
        +device_id: bigint
        +schema_version_topic_id: bigint
        +user_id: bigint
        +command_payload: json
        +status: CommandStatus
        +response_payload: json
        +error_message: text
        +sent_at: timestamp
        +acknowledged_at: timestamp
        +completed_at: timestamp
        +device()
        +topic()
        +user()
    }
    
    Device "1" -- "1" DeviceDesiredState : targets
    Device "1" -- "many" DeviceCommandLog : receives
```

**Key Responsibilities**:
- Store desired device state for reconciliation
- Log command execution with status tracking
- Track command acknowledgment and completion
- Audit trail for device control actions

**Command Flow**:
1. User initiates command via UI
2. Validate command against schema
3. Create command log entry
4. Publish to device via protocol
5. Track acknowledgment and completion
6. Update desired state if applicable

---

## Cross-Domain Interactions

```mermaid
sequenceDiagram
    participant Admin
    participant DeviceMgmt as Device Management
    participant Schema as Device Schema
    participant Device
    participant Telemetry
    participant Control as Device Control
    
    Admin->>DeviceMgmt: Create DeviceType
    Admin->>Schema: Define Schema + Version
    Schema->>Schema: Add Parameters
    Admin->>DeviceMgmt: Register Device
    DeviceMgmt->>Schema: Pin to Schema Version
    
    Device->>Telemetry: Send telemetry data
    Telemetry->>Schema: Validate against schema
    Telemetry->>Telemetry: Store log
    
    Admin->>Control: Send command
    Control->>Schema: Validate command
    Control->>Device: Execute command
    Device->>Control: Acknowledge
```

## Design Patterns

- **Repository Pattern**: Models act as repositories for domain entities
- **Service Layer**: Domain-specific services handle complex business logic
- **Event-Driven**: Domain events trigger cross-domain actions
- **Immutability**: Active schema versions are immutable
- **Soft Deletes**: Preserves data integrity and audit trail
