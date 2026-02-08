# Command & Control Flow

## Overview

This document describes how commands are sent from the platform to IoT devices and how device state is managed.

## High-Level Flow

```mermaid
graph TB
    subgraph "Presentation Layer"
        UI[Filament UI]
        USER[User Action]
    end
    
    subgraph "Application Layer"
        CTL[Controller]
        VAL[Command Validator]
        JOB[SendCommand Job]
    end
    
    subgraph "Domain Layer"
        CMD[DeviceCommandLog]
        STATE[DeviceDesiredState]
    end
    
    subgraph "Infrastructure Layer"
        QUEUE[Queue System]
        DB[(Database)]
        MQTT[MQTT Client]
        HTTP[HTTP Client]
    end
    
    subgraph "Device Layer"
        DEV[IoT Device]
    end
    
    USER --> UI
    UI --> CTL
    CTL --> VAL
    VAL --> CMD
    VAL --> STATE
    CMD --> DB
    STATE --> DB
    VAL --> QUEUE
    QUEUE --> JOB
    JOB --> MQTT
    JOB --> HTTP
    MQTT --> DEV
    HTTP --> DEV
    DEV --> MQTT
    MQTT --> JOB
    JOB --> CMD
```

## Detailed Command Flow

```mermaid
sequenceDiagram
    participant User
    participant UI as Filament UI
    participant Controller
    participant Validator as Command Validator
    participant DB as Database
    participant Queue
    participant Job as SendCommand Job
    participant Protocol as MQTT/HTTP Client
    participant Device
    
    User->>UI: Initiate command
    UI->>UI: Build command form
    User->>UI: Submit command
    
    UI->>Controller: POST command
    Controller->>Validator: Validate command
    
    Validator->>Validator: Check schema topic
    Validator->>Validator: Validate payload schema
    
    alt Valid command
        Validator->>DB: Create CommandLog<br/>(status: pending)
        Validator->>DB: Update DesiredState
        Validator->>Queue: Dispatch SendCommand job
        Controller->>UI: Command queued
        
        Queue->>Job: Process job
        Job->>Job: Get device protocol
        Job->>Job: Build topic/endpoint
        
        alt MQTT Protocol
            Job->>Protocol: Publish to topic
            Protocol->>Device: MQTT message
        else HTTP Protocol
            Job->>Protocol: POST to endpoint
            Protocol->>Device: HTTP request
        end
        
        Job->>DB: Update CommandLog<br/>(status: sent)
        
        alt Device acknowledgment
            Device->>Protocol: Acknowledge
            Protocol->>Job: Ack received
            Job->>DB: Update CommandLog<br/>(status: acknowledged)
            
            Device->>Device: Execute command
            Device->>Protocol: Response/completion
            Protocol->>Job: Response received
            Job->>DB: Update CommandLog<br/>(status: completed)
        else Timeout
            Job->>DB: Update CommandLog<br/>(status: timeout)
        end
    else Invalid command
        Validator->>UI: Validation error
    end
```

## Component Breakdown

### 1. Command Initiation

Commands are initiated through Filament UI actions:

```mermaid
graph LR
    A[Device View] --> B{Command Type}
    B -->|Set State| C[Update Desired State]
    B -->|Execute Action| D[One-time Command]
    C --> E[Command Form]
    D --> E
    E --> F[Submit]
    F --> G[Validate]
```

**Command Types**:
- **State Commands**: Set persistent desired state (e.g., set temperature to 22°C)
- **Action Commands**: One-time actions (e.g., reboot, calibrate)
- **Configuration Commands**: Update device settings (e.g., sampling rate)

### 2. Command Validation

```mermaid
stateDiagram-v2
    [*] --> CheckDevice
    CheckDevice --> DeviceInactive: Device inactive
    CheckDevice --> CheckTopic: Device active
    CheckTopic --> TopicNotFound: Topic not in schema
    CheckTopic --> ValidatePayload: Topic found
    ValidatePayload --> ValidateSchema
    ValidateSchema --> CheckDirection
    CheckDirection --> WrongDirection: Not outbound topic
    CheckDirection --> Valid: Outbound topic
    
    Valid --> [*]
    DeviceInactive --> [*]: Reject
    TopicNotFound --> [*]: Reject
    WrongDirection --> [*]: Reject
```

**Validation Steps**:
1. Device exists and is active
2. Topic exists in device's schema version
3. Topic direction is `outbound` or `bidirectional`
4. Payload matches topic's payload schema
5. User has permission to send commands

### 3. Command Persistence

**DeviceCommandLog Lifecycle**:

```mermaid
stateDiagram-v2
    [*] --> Pending: Command created
    Pending --> Sent: Published to device
    Sent --> Acknowledged: Device ACK received
    Sent --> Timeout: No response
    Acknowledged --> Completed: Execution finished
    Acknowledged --> Failed: Execution error
    
    Timeout --> [*]
    Completed --> [*]
    Failed --> [*]
```

**DeviceCommandLog Fields**:
```json
{
  "id": 123,
  "device_id": 456,
  "schema_version_topic_id": 12,
  "user_id": 789,
  "command_payload": {
    "action": "set_temperature",
    "value": 22.0,
    "unit": "celsius"
  },
  "status": "completed",
  "response_payload": {
    "success": true,
    "previous_value": 20.0,
    "new_value": 22.0
  },
  "error_message": null,
  "sent_at": "2026-02-08T01:30:00Z",
  "acknowledged_at": "2026-02-08T01:30:01Z",
  "completed_at": "2026-02-08T01:30:03Z"
}
```

### 4. Desired State Management

```mermaid
graph TB
    A[Command Received] --> B{State Command?}
    B -->|Yes| C[Update DesiredState]
    B -->|No| D[Execute Only]
    C --> E[Store Desired State]
    E --> F[Send Command]
    D --> F
    F --> G{Device Responds?}
    G -->|Success| H[Mark Reconciled]
    G -->|Failure| I[State Drift Detected]
    H --> J[Update reconciled_at]
```

**DeviceDesiredState Example**:
```json
{
  "id": 1,
  "device_id": 456,
  "desired_state": {
    "temperature_setpoint": 22.0,
    "mode": "cooling",
    "fan_speed": "auto",
    "enabled": true
  },
  "reconciled_at": "2026-02-08T01:30:03Z"
}
```

### 5. Protocol-Specific Publishing

#### MQTT Protocol

```mermaid
graph LR
    A[Build MQTT Message] --> B[Topic Template]
    B --> C[Replace Placeholders]
    C --> D[device/UUID/command]
    D --> E[Set QoS Level]
    E --> F[Set Retain Flag]
    F --> G[Publish]
    G --> H{ACK Required?}
    H -->|Yes| I[Wait for ACK Topic]
    H -->|No| J[Mark Sent]
```

**MQTT Topic Pattern**:
```
device/{device_uuid}/command
device/{device_uuid}/config
```

**MQTT Message**:
```json
{
  "command_id": "cmd_123",
  "timestamp": "2026-02-08T01:30:00Z",
  "payload": {
    "action": "set_temperature",
    "value": 22.0
  }
}
```

**ACK Topic** (device responds to):
```
device/{device_uuid}/command/ack
```

#### HTTP Protocol

```mermaid
graph LR
    A[Build HTTP Request] --> B[Endpoint Template]
    B --> C[Replace Placeholders]
    C --> D[https://device.example.com/api/command]
    D --> E[Add Headers]
    E --> F[Add Auth]
    F --> G[POST Request]
    G --> H{Response Code?}
    H -->|2xx| I[Success]
    H -->|4xx/5xx| J[Error]
```

**HTTP Endpoint Pattern**:
```
https://{device_host}/api/v1/command
```

**HTTP Request**:
```http
POST /api/v1/command HTTP/1.1
Host: device.example.com
Authorization: Bearer {device_token}
Content-Type: application/json

{
  "command_id": "cmd_123",
  "action": "set_temperature",
  "value": 22.0
}
```

### 6. Device Acknowledgment

```mermaid
sequenceDiagram
    participant Platform
    participant Device
    participant ACK as ACK Handler
    participant DB
    
    Platform->>Device: Send command
    Device->>Device: Receive command
    Device->>Platform: Send ACK
    Platform->>ACK: Process ACK
    ACK->>DB: Update status: acknowledged
    
    Device->>Device: Execute command
    
    alt Success
        Device->>Platform: Send completion
        Platform->>ACK: Process completion
        ACK->>DB: Update status: completed
        ACK->>DB: Update reconciled_at
    else Failure
        Device->>Platform: Send error
        Platform->>ACK: Process error
        ACK->>DB: Update status: failed
        ACK->>DB: Store error_message
    end
```

### 7. State Reconciliation

The platform monitors state drift between desired and reported state:

```mermaid
graph TB
    A[Periodic Check] --> B[Get DesiredState]
    B --> C[Get Latest Telemetry]
    C --> D{States Match?}
    D -->|Yes| E[States Reconciled]
    D -->|No| F{Auto Retry?}
    E --> G[Update reconciled_at]
    F -->|Yes| H[Resend Command]
    F -->|No| I[Alert Admin]
    H --> J[Increment Retry Count]
    J --> K{Max Retries?}
    K -->|No| A
    K -->|Yes| I
```

## Command Topics Configuration

Topics are defined in the `SchemaVersionTopic` model:

```mermaid
classDiagram
    class SchemaVersionTopic {
        +key: string
        +label: string
        +direction: TopicDirection
        +suffix: string
        +description: text
        +qos: int
        +retain: boolean
    }
    
    class TopicDirection {
        <<enumeration>>
        INBOUND
        OUTBOUND
        BIDIRECTIONAL
    }
    
    SchemaVersionTopic --> TopicDirection
```

**Example Topic Definitions**:

| Key | Direction | Suffix | QoS | Description |
|-----|-----------|--------|-----|-------------|
| telemetry | inbound | telemetry | 1 | Device sensor data |
| command | outbound | command | 2 | Control commands |
| config | outbound | config | 2 | Configuration updates |
| status | bidirectional | status | 1 | Device status |

## Error Handling

```mermaid
graph TD
    A[Error Occurred] --> B{Error Type?}
    B -->|Network Timeout| C[Retry with backoff]
    B -->|Device Offline| D[Queue for later]
    B -->|Invalid Response| E[Mark as failed]
    B -->|Permission Denied| F[Reject command]
    
    C --> G{Max Retries?}
    G -->|No| H[Retry]
    G -->|Yes| I[Mark timeout]
    
    D --> J[Set retry schedule]
    E --> K[Log error details]
    F --> L[Notify user]
```

## Security Considerations

1. **Command Authorization**: Users must have appropriate permissions
2. **Payload Validation**: All commands validated against schema
3. **Rate Limiting**: Prevent command flooding
4. **Audit Trail**: All commands logged with user, timestamp, result
5. **Device Authentication**: Devices verify command source

## Performance Considerations

- **Asynchronous Processing**: Commands queued and sent asynchronously
- **Batch Commands**: Support sending same command to multiple devices
- **Timeout Management**: Configurable timeouts per device type
- **Retry Strategy**: Exponential backoff for failed commands

## Future Enhancements

- Command scheduling and cron-like execution
- Conditional commands based on device state
- Command templates and saved presets
- Bulk command operations
- Command impact analysis before execution
- Rollback mechanisms for configuration changes
