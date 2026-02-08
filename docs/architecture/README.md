# Architecture Documentation

Welcome to the LMU IoT Portal architecture documentation. This guide provides comprehensive information about the platform's design, components, and data flows.

## Documentation Structure

### 1. [Architecture Overview](./01-overview.md)
**Purpose**: High-level introduction to the platform architecture

**Contents**:
- Technology stack overview
- High-level architecture diagram
- System components explanation
- Multi-tenancy model
- Key design principles
- Data flow overview

**For**: New developers, stakeholders, system architects

---

### 2. [Domain Layer Architecture](./02-domain-layer.md)
**Purpose**: Deep dive into the domain-driven design structure

**Contents**:
- Domain organization and boundaries
- Model relationships and responsibilities
- Cross-domain interactions
- Design patterns used
- Detailed class diagrams per domain

**Domains Covered**:
- Shared (User, Organization)
- Authorization (Roles, Permissions)
- Device Management (DeviceType, Device)
- Device Schema (Schema versioning system)
- Telemetry (Data logging and validation)
- Device Control (Commands, desired state)

**For**: Backend developers, feature implementers

---

### 3. [Telemetry Data Flow](./03-telemetry-flow.md)
**Purpose**: Understand how device data flows through the system

**Contents**:
- End-to-end telemetry flow diagrams
- MQTT/HTTP ingestion processes
- Parameter extraction and validation
- Derived parameter computation
- Real-time broadcasting
- Error handling strategies

**For**: IoT integrators, data engineers, developers working on telemetry features

---

### 4. [Command & Control Flow](./04-command-flow.md)
**Purpose**: Understand device command execution

**Contents**:
- Command initiation and validation
- Protocol-specific publishing (MQTT/HTTP)
- Device acknowledgment handling
- Desired state management
- State reconciliation
- Command audit logging

**For**: Developers working on device control features, IoT integrators

---

### 5. [Authentication & Authorization](./05-authentication-authorization.md)
**Purpose**: Security and access control mechanisms

**Contents**:
- User authentication flow
- Multi-tenant authorization model
- Role-based access control (RBAC)
- Organization context and scoping
- Filament policy integration
- Super admin privileges
- Security best practices

**For**: Security engineers, developers implementing access control

---

### 6. [Deployment & Infrastructure](./06-deployment-infrastructure.md)
**Purpose**: Production deployment and infrastructure setup

**Contents**:
- Deployment architecture diagrams
- Container architecture (Docker/Kubernetes)
- Service components (web, workers, MQTT, WebSocket)
- Database configuration and replication
- Monitoring and observability
- Security measures
- Disaster recovery
- CI/CD pipeline
- Scaling strategies

**For**: DevOps engineers, system administrators, platform engineers

---

### 7. [Data Model & Database Schema](./07-data-model.md)
**Purpose**: Complete database schema reference

**Contents**:
- Complete entity-relationship diagram
- Domain-specific data model views
- Table details with indexes and constraints
- JSONB field schemas
- Data retention policies
- Database optimization strategies

**For**: Database administrators, backend developers, data analysts

---

## Quick Navigation by Role

### For New Developers
Start here to understand the platform:
1. [Architecture Overview](./01-overview.md) - Get the big picture
2. [Domain Layer](./02-domain-layer.md) - Understand code organization
3. [Data Model](./07-data-model.md) - Learn the database structure

### For IoT Integration Engineers
Focus on data flows:
1. [Telemetry Flow](./03-telemetry-flow.md) - Device data ingestion
2. [Command Flow](./04-command-flow.md) - Device control
3. [Data Model](./07-data-model.md) - Schema and validation rules

### For DevOps Engineers
Infrastructure and deployment:
1. [Deployment & Infrastructure](./06-deployment-infrastructure.md) - Complete deployment guide
2. [Architecture Overview](./01-overview.md) - System components

### For Security Engineers
Security and access control:
1. [Authentication & Authorization](./05-authentication-authorization.md) - Complete security model
2. [Deployment & Infrastructure](./06-deployment-infrastructure.md) - Network security

### For Frontend Developers
UI and real-time features:
1. [Architecture Overview](./01-overview.md) - Presentation layer
2. [Telemetry Flow](./03-telemetry-flow.md) - Real-time data updates
3. [Authentication & Authorization](./05-authentication-authorization.md) - User permissions

---

## Key Architecture Principles

### 1. Domain-Driven Design
- Code organized by business domains
- Clear boundaries and responsibilities
- Domain models encapsulate business logic

### 2. Multi-Tenancy
- Organization-based data isolation
- Tenant scoping enforced at middleware and query level
- Users can belong to multiple organizations

### 3. Schema Versioning
- Immutable active schema versions
- Devices pin to specific schema version
- Schema evolution through new versions

### 4. Event-Driven Architecture
- Asynchronous processing for telemetry
- Real-time broadcasting via WebSockets
- Queue-based job processing

### 5. Type Safety
- Strong typing in PHP 8.4
- Eloquent models with strict types
- Protocol configs as PHP classes, not raw JSON

### 6. Security by Default
- RBAC with organization scoping
- All state changes protected by CSRF
- Audit logging for sensitive operations

---

## Technology Stack Summary

| Layer | Technologies |
|-------|-------------|
| **Frontend** | Filament 5, Livewire 4, Alpine.js, Tailwind CSS |
| **Backend** | Laravel 12, PHP 8.4 |
| **Database** | PostgreSQL 17 with JSONB |
| **Cache/Queue** | Redis 7, Laravel Horizon |
| **Real-time** | Laravel Reverb (WebSockets), NATS |
| **IoT Protocols** | MQTT (php-mqtt/client), HTTP |
| **Testing** | Pest 4, PHPStan Level 8 |
| **Code Quality** | Laravel Pint, Rector |
| **Containerization** | Docker, Docker Compose, Kubernetes |

---

## Diagram Conventions

Throughout this documentation, we use Mermaid diagrams with the following conventions:

### Component Diagrams
- **Rectangles**: Services/Components
- **Cylinders**: Databases/Storage
- **Arrows**: Data flow or dependencies

### Sequence Diagrams
- **Participants**: Systems or actors
- **Solid arrows**: Synchronous calls
- **Dashed arrows**: Asynchronous or response messages

### ER Diagrams
- **Entities**: Database tables
- **Lines**: Relationships
  - `||--||`: One-to-one
  - `||--o{`: One-to-many
  - `}o--o{`: Many-to-many

### State Diagrams
- **Rectangles**: States
- **Arrows**: Transitions
- **Diamond**: Conditional branches

---

## Contributing to Documentation

When updating this documentation:

1. **Keep diagrams updated**: Update Mermaid diagrams when architecture changes
2. **Minimize code examples**: Focus on diagrams and explanations
3. **Use consistent formatting**: Follow the established structure
4. **Cross-reference**: Link related sections
5. **Version control**: Note major changes in commit messages

---

## Related Documentation

- **Planning Documents**: `/plan` directory - Project scope, ERDs, backlog
- **README**: Root `README.md` - Quick start and setup
- **CONTRIBUTING**: `CONTRIBUTING.md` - Git workflow and conventions
- **API Documentation**: (Future) API endpoint reference

---

## Feedback & Questions

For questions about the architecture:
- Review the appropriate documentation section
- Check the planning documents in `/plan`
- Consult with the development team
- Update this documentation when clarifications are made

---

*Last Updated: February 2026*
*Documentation Version: 1.0*
