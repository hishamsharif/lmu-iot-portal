# Architecture Overview

## Introduction

The LMU IoT Portal is a **multi-tenant IoT device management platform** that enables organizations to:
- Onboard and manage IoT devices
- Define flexible device schemas with versioning
- Ingest telemetry data via MQTT and HTTP
- Send commands and control devices
- Monitor device states in real-time

## Technology Stack

```mermaid
graph TB
    subgraph "Frontend Layer"
        A[Filament 5 Admin Panel]
        B[Livewire 4 Components]
        C[Alpine.js + Tailwind CSS]
    end
    
    subgraph "Backend Layer"
        D[Laravel 12]
        E[PHP 8.4]
    end
    
    subgraph "Data Layer"
        F[PostgreSQL + JSONB]
        G[Redis Queue]
        H[Laravel Horizon]
    end
    
    subgraph "Real-time Layer"
        I[Laravel Reverb]
        J[MQTT Broker]
        K[NATS Messaging]
    end
    
    subgraph "Quality & Testing"
        L[Pest 4 Testing]
        M[PHPStan Level 8]
        N[Laravel Pint]
    end
    
    A --> B
    B --> C
    A --> D
    D --> E
    D --> F
    D --> G
    G --> H
    D --> I
    D --> J
    D --> K
    D --> L
    D --> M
    D --> N
```

## High-Level Architecture

```mermaid
graph TB
    subgraph "External Systems"
        IOT[IoT Devices]
        USERS[End Users]
        ADMINS[System Admins]
    end
    
    subgraph "Presentation Layer"
        ADMIN[Admin Panel<br/>Filament]
        PORTAL[Tenant Portal<br/>Filament]
    end
    
    subgraph "Application Layer"
        HTTP[HTTP Controllers]
        JOBS[Queue Jobs<br/>Horizon]
        EVENTS[Event System]
    end
    
    subgraph "Domain Layer"
        AUTH[Authorization]
        DEVICE[Device Management]
        SCHEMA[Device Schema]
        TELEMETRY[Telemetry]
        CONTROL[Device Control]
        SHARED[Shared Domain]
    end
    
    subgraph "Infrastructure Layer"
        DB[(PostgreSQL)]
        CACHE[(Redis)]
        MQTT[MQTT Client]
        NATS[NATS Publisher]
    end
    
    IOT -->|MQTT/HTTP| MQTT
    IOT -->|MQTT/HTTP| HTTP
    USERS --> PORTAL
    ADMINS --> ADMIN
    
    ADMIN --> HTTP
    PORTAL --> HTTP
    
    HTTP --> AUTH
    HTTP --> DEVICE
    HTTP --> SCHEMA
    HTTP --> TELEMETRY
    HTTP --> CONTROL
    
    MQTT --> JOBS
    JOBS --> EVENTS
    EVENTS --> TELEMETRY
    
    AUTH --> SHARED
    DEVICE --> SHARED
    SCHEMA --> DEVICE
    TELEMETRY --> DEVICE
    CONTROL --> DEVICE
    
    SHARED --> DB
    AUTH --> DB
    DEVICE --> DB
    SCHEMA --> DB
    TELEMETRY --> DB
    CONTROL --> DB
    
    JOBS --> CACHE
    EVENTS --> NATS
```

## System Components

### 1. Presentation Layer
- **Admin Panel**: Global device type catalog management, system administration
- **Tenant Portal**: Organization-scoped device management, monitoring, and control

### 2. Application Layer
- **HTTP Controllers**: Handle incoming HTTP requests
- **Queue Jobs**: Process asynchronous tasks via Laravel Horizon
- **Event System**: Real-time event broadcasting and handling

### 3. Domain Layer
Organized by business domains following Domain-Driven Design principles:

- **Shared**: Common models (User, Organization)
- **Authorization**: Role-based access control with organization-level permissions
- **Device Management**: Device types, devices, and lifecycle management
- **Device Schema**: Flexible schema versioning system
- **Telemetry**: Incoming data validation and logging
- **Device Control**: Commands and desired state management

### 4. Infrastructure Layer
- **PostgreSQL**: Primary data store with JSONB for flexible schemas
- **Redis**: Queue backend and caching
- **MQTT Client**: Subscribes to device messages
- **NATS**: Publishes events to external systems

## Multi-Tenancy Model

```mermaid
graph LR
    subgraph "Organization A"
        U1[Users]
        D1[Devices]
        R1[Roles]
    end
    
    subgraph "Organization B"
        U2[Users]
        D2[Devices]
        R2[Roles]
    end
    
    subgraph "Global Catalog"
        DT[Device Types]
        DS[Device Schemas]
    end
    
    O1[Organization A] --> U1
    O1 --> D1
    O1 --> R1
    
    O2[Organization B] --> U2
    O2 --> D2
    O2 --> R2
    
    D1 -.->|references| DT
    D2 -.->|references| DT
    
    DT --> DS
```

### Tenant Scoping
- All organization-specific data is scoped by `organization_id`
- Device types can be global (shared catalog) or organization-specific
- Users can belong to multiple organizations with different roles per organization
- Middleware automatically applies tenant scopes to queries

## Key Design Principles

1. **Tenant Boundary**: Everything attaches to `organizations.id`
2. **Versioned Schema Contract**: Schema changes create new versions; active versions are immutable
3. **Relational, Normalized**: Child tables reference parents; minimal data redundancy
4. **Stable Device Identity**: `devices.uuid` is the stable public identifier
5. **Type-Safe Configuration**: Protocol configs use PHP classes, not raw JSON
6. **Event-Driven Architecture**: Asynchronous processing for telemetry ingestion

## Data Flow Overview

```mermaid
sequenceDiagram
    participant Device
    participant MQTT
    participant Queue
    participant Validator
    participant DB
    participant UI
    
    Device->>MQTT: Publish telemetry
    MQTT->>Queue: Queue processing job
    Queue->>Validator: Validate against schema
    Validator->>DB: Store telemetry log
    DB->>UI: Real-time update
```

See detailed data flow diagrams in:
- [Telemetry Flow](./03-telemetry-flow.md)
- [Command Flow](./04-command-flow.md)
