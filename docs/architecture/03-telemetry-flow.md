# Telemetry Data Flow

## Overview

This document describes how telemetry data flows from IoT devices through the platform to storage and real-time updates.

## High-Level Flow

```mermaid
graph TB
    subgraph "Device Layer"
        D1[IoT Device 1]
        D2[IoT Device 2]
        D3[IoT Device 3]
    end
    
    subgraph "Ingestion Layer"
        MQTT[MQTT Broker]
        HTTP[HTTP Endpoint]
    end
    
    subgraph "Processing Layer"
        SUB[MQTT Subscriber]
        Q[Queue Job]
        VAL[Validator Service]
    end
    
    subgraph "Storage Layer"
        DB[(PostgreSQL)]
        TSDB[(Time-Series DB<br/>Future)]
    end
    
    subgraph "Real-time Layer"
        WS[WebSocket<br/>Reverb]
        NATS[NATS Publisher]
    end
    
    subgraph "Presentation Layer"
        UI[Filament UI]
    end
    
    D1 -->|MQTT Publish| MQTT
    D2 -->|MQTT Publish| MQTT
    D3 -->|HTTP POST| HTTP
    
    MQTT --> SUB
    HTTP --> Q
    SUB --> Q
    
    Q --> VAL
    VAL --> DB
    VAL --> WS
    VAL --> NATS
    
    WS --> UI
    
    style VAL fill:#f9f,stroke:#333,stroke-width:4px
```

## Detailed Telemetry Flow

```mermaid
sequenceDiagram
    participant Device
    participant Broker as MQTT Broker
    participant Subscriber as MQTT Subscriber
    participant Queue as Laravel Queue
    participant Job as ProcessTelemetry Job
    participant Validator as Schema Validator
    participant Extractor as Parameter Extractor
    participant Evaluator as JsonLogic Evaluator
    participant DB as Database
    participant Event as Event System
    participant UI as Real-time UI
    
    Device->>Broker: Publish to topic<br/>device/{uuid}/telemetry
    Broker->>Subscriber: Message received
    Subscriber->>Queue: Dispatch job
    
    Queue->>Job: Process message
    Job->>Job: Identify device by UUID
    Job->>Job: Get schema version
    Job->>Job: Match topic
    
    Job->>Validator: Validate structure
    alt Valid structure
        Validator->>Extractor: Extract parameters
        loop For each parameter
            Extractor->>Extractor: Apply JSON path
            Extractor->>Evaluator: Apply mutation
            Extractor->>Validator: Validate rules
        end
        
        Validator->>Evaluator: Compute derived params
        
        Validator->>DB: Store telemetry log<br/>(validated)
        Validator->>Event: Emit TelemetryReceived
        Event->>UI: Broadcast update
    else Invalid structure
        Validator->>DB: Store telemetry log<br/>(validation failed)
        Validator->>Event: Emit ValidationFailed
    end
```

## Component Breakdown

### 1. Device Publishing

Devices publish to topic patterns defined in their schema:

**MQTT Topic Pattern**:
```
device/{device_uuid}/{topic_suffix}
```

**Example Topics**:
- `device/550e8400-e29b-41d4-a716-446655440000/telemetry`
- `device/550e8400-e29b-41d4-a716-446655440000/status`
- `device/550e8400-e29b-41d4-a716-446655440000/error`

**Payload Example** (JSON):
```json
{
  "timestamp": "2026-02-08T01:30:00Z",
  "voltage": 230.5,
  "current": 15.2,
  "power": 3503.6,
  "temperature": 42.8,
  "errors": []
}
```

### 2. MQTT Subscriber

The MQTT subscriber runs as a long-lived process:

```mermaid
graph LR
    A[Start Subscriber] --> B[Connect to Broker]
    B --> C[Subscribe to Pattern<br/>device/+/+]
    C --> D[Listen for Messages]
    D --> E{Message Received?}
    E -->|Yes| F[Dispatch to Queue]
    F --> D
    E -->|No| D
```

**Key Features**:
- Subscribes to wildcard pattern `device/+/+`
- Extracts device UUID and topic suffix from received topic
- Dispatches job to queue for async processing
- Handles reconnection and error recovery

### 3. Queue Processing

**Job**: `ProcessDeviceTelemetry`

```mermaid
stateDiagram-v2
    [*] --> ReceiveJob
    ReceiveJob --> LookupDevice
    LookupDevice --> DeviceNotFound: Device not found
    LookupDevice --> GetSchema: Device found
    GetSchema --> MatchTopic
    MatchTopic --> TopicNotFound: Topic not matched
    MatchTopic --> ValidatePayload: Topic matched
    ValidatePayload --> ExtractParameters
    ExtractParameters --> ComputeDerived
    ComputeDerived --> StoreLog
    StoreLog --> EmitEvent
    EmitEvent --> [*]
    
    DeviceNotFound --> LogError
    TopicNotFound --> LogError
    LogError --> [*]
```

### 4. Parameter Extraction

For each parameter defined in the schema:

```mermaid
graph TD
    A[Raw Payload] --> B[Apply JSON Path]
    B --> C{Value Found?}
    C -->|Yes| D[Apply Mutation]
    C -->|No| E{Required?}
    D --> F[Type Coercion]
    E -->|Yes| G[Validation Failed]
    E -->|No| H[Use Default/Null]
    F --> I[Validate Rules]
    I --> J{Valid?}
    J -->|Yes| K[Store Value]
    J -->|No| G
    H --> K
    K --> L[Next Parameter]
    G --> M[Log Error]
```

**JSON Path Examples**:
- `$.voltage` → extracts top-level voltage field
- `$.sensors[0].temperature` → extracts from array
- `$.data.metrics.power` → nested object navigation

**Mutation Examples**:
- Convert Fahrenheit to Celsius: `{"*": [{"var": "value"}, 0.5556]}`
- Scale value: `{"/": [{"var": "value"}, 1000]}`
- Clamp range: `{"min": [{"max": [{"var": "value"}, 0]}, 100]}`

### 5. Derived Parameter Computation

Computed from other parameters using JsonLogic expressions:

```mermaid
graph LR
    A[Extracted Values] --> B[Check Dependencies]
    B --> C{All Available?}
    C -->|Yes| D[Evaluate Expression]
    C -->|No| E[Skip/Null]
    D --> F[Store Computed Value]
    F --> G[Next Derived Param]
    E --> G
```

**Example Derived Parameter**:
- **Power Factor**: `{"*": [{"var": "power"}, {"/": [1, {"*": [{"var": "voltage"}, {"var": "current"}]}]}]}`
- **Energy (kWh)**: `{"/": [{"var": "power"}, 1000]}`

### 6. Storage

**DeviceTelemetryLog Record**:
```json
{
  "id": "uuid-v7",
  "device_id": 123,
  "device_schema_version_id": 45,
  "schema_version_topic_id": 12,
  "validation_status": "validated",
  "raw_payload": {/* original JSON */},
  "transformed_values": {
    "voltage": 230.5,
    "current": 15.2,
    "power": 3503.6,
    "temperature": 42.8,
    "power_factor": 0.99,
    "energy_kwh": 3.5036
  },
  "recorded_at": "2026-02-08T01:30:00Z",
  "received_at": "2026-02-08T01:30:01Z"
}
```

### 7. Real-time Broadcasting

```mermaid
sequenceDiagram
    participant Job
    participant Event as TelemetryReceived Event
    participant Listener as Event Listener
    participant Reverb as Laravel Reverb
    participant NATS
    participant UI
    
    Job->>Event: Emit event
    Event->>Listener: Handle event
    
    par Broadcast to UI
        Listener->>Reverb: Broadcast to channel<br/>organization.{id}.device.{uuid}
        Reverb->>UI: WebSocket push
    and Publish to NATS
        Listener->>NATS: Publish message
    end
```

**Broadcasting Channels**:
- Organization-level: `organization.{org_id}.telemetry`
- Device-level: `organization.{org_id}.device.{device_uuid}`

## Validation States

```mermaid
stateDiagram-v2
    [*] --> Pending: Received
    Pending --> Validated: All rules pass
    Pending --> PartiallyValidated: Some params fail
    Pending --> Failed: Critical params fail
    Pending --> Error: Processing error
    
    Validated --> [*]
    PartiallyValidated --> [*]
    Failed --> [*]
    Error --> [*]
```

**Validation Status Values**:
- `validated`: All parameters passed validation
- `partially_validated`: Non-critical parameters failed
- `failed`: Critical parameters failed or structure invalid
- `error`: Processing error occurred

## Error Handling

```mermaid
graph TD
    A[Error Detected] --> B{Error Type?}
    B -->|Device Not Found| C[Log Warning]
    B -->|Topic Not Matched| D[Log Info]
    B -->|Validation Failed| E[Store Failed Log]
    B -->|Processing Error| F[Store Error Log]
    
    C --> G[Optionally Alert Admin]
    D --> H[Update Device Metrics]
    E --> I[Emit Validation Event]
    F --> J[Retry or Dead Letter]
```

## Performance Considerations

### Optimization Strategies

1. **Queue Workers**: Run multiple workers for parallel processing
2. **Batch Processing**: Group parameter extractions where possible
3. **Caching**: Cache schema versions and parameter definitions
4. **Indexing**: Database indexes on device_id, device_uuid, recorded_at
5. **Partitioning**: Future time-series partitioning for telemetry logs

### Monitoring Metrics

- Messages received per second
- Processing latency (received → stored)
- Validation success rate
- Queue depth and worker utilization
- Failed message rate

## Future Enhancements

- Time-series database integration (TimescaleDB/InfluxDB)
- Aggregation and downsampling
- Advanced anomaly detection
- Stream processing for real-time analytics
- Message deduplication
