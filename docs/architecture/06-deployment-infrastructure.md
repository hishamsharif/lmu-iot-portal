# Deployment & Infrastructure

## Overview

The LMU IoT Portal is designed to be deployed in containerized environments with support for horizontal scaling and high availability.

## Deployment Architecture

```mermaid
graph TB
    subgraph "External Layer"
        LB[Load Balancer<br/>nginx/HAProxy]
        CDN[CDN<br/>Static Assets]
    end
    
    subgraph "Application Layer"
        WEB1[Web Server 1<br/>PHP-FPM + nginx]
        WEB2[Web Server 2<br/>PHP-FPM + nginx]
        WEB3[Web Server 3<br/>PHP-FPM + nginx]
    end
    
    subgraph "Queue Worker Layer"
        Q1[Queue Worker 1<br/>Horizon]
        Q2[Queue Worker 2<br/>Horizon]
        Q3[Queue Worker 3<br/>Horizon]
    end
    
    subgraph "Real-time Layer"
        MQTT[MQTT Subscriber<br/>Long-running Process]
        WS[WebSocket Server<br/>Reverb]
    end
    
    subgraph "Data Layer"
        DB[(Primary PostgreSQL)]
        DB_READ[(Read Replica)]
        REDIS[(Redis Cluster)]
    end
    
    subgraph "Message Layer"
        BROKER[MQTT Broker<br/>Mosquitto/EMQX]
        NATS_SRV[NATS Server]
    end
    
    LB --> WEB1
    LB --> WEB2
    LB --> WEB3
    CDN --> WEB1
    
    WEB1 --> DB
    WEB2 --> DB
    WEB3 --> DB
    WEB1 --> DB_READ
    WEB2 --> DB_READ
    WEB3 --> DB_READ
    
    WEB1 --> REDIS
    WEB2 --> REDIS
    WEB3 --> REDIS
    
    Q1 --> REDIS
    Q2 --> REDIS
    Q3 --> REDIS
    Q1 --> DB
    Q2 --> DB
    Q3 --> DB
    
    MQTT --> BROKER
    MQTT --> REDIS
    MQTT --> DB
    
    WS --> REDIS
    WEB1 --> WS
    WEB2 --> WS
    WEB3 --> WS
    
    Q1 --> NATS_SRV
    Q2 --> NATS_SRV
    Q3 --> NATS_SRV
```

## Container Architecture

```mermaid
graph TB
    subgraph "Docker Compose Stack"
        subgraph "Application Containers"
            APP[app<br/>Laravel + PHP 8.4]
            NGINX[nginx<br/>Web Server]
        end
        
        subgraph "Worker Containers"
            HORIZON[horizon<br/>Queue Worker]
            SCHEDULER[scheduler<br/>Cron Jobs]
            MQTT_SUB[mqtt-subscriber<br/>MQTT Consumer]
            REVERB_WS[reverb<br/>WebSocket Server]
        end
        
        subgraph "Infrastructure Containers"
            PG[(postgres<br/>PostgreSQL 17)]
            RD[(redis<br/>Redis 7)]
            MOSQUITTO[mosquitto<br/>MQTT Broker]
            NATS_C[nats<br/>NATS Messaging]
        end
    end
    
    NGINX --> APP
    APP --> PG
    APP --> RD
    HORIZON --> PG
    HORIZON --> RD
    MQTT_SUB --> MOSQUITTO
    MQTT_SUB --> RD
    MQTT_SUB --> PG
    REVERB_WS --> RD
    SCHEDULER --> APP
    HORIZON --> NATS_C
```

## Environment Configuration

### Development Environment

```mermaid
graph LR
    A[Developer Machine] --> B[Docker Compose]
    B --> C[Local Containers]
    C --> D[Hot Reload]
    D --> A
```

**docker-compose.yml** stack:
- `app`: Laravel application with Xdebug
- `nginx`: Web server on port 80
- `postgres`: PostgreSQL 17 with persistent volume
- `redis`: Redis 7 for cache and queues
- `horizon`: Queue worker with auto-restart
- `scheduler`: Laravel scheduler
- `mosquitto`: MQTT broker on port 1883
- `mailpit`: Email testing on port 8025

### Production Environment

```mermaid
graph TB
    subgraph "Production Infrastructure"
        K8S[Kubernetes Cluster]
        
        subgraph "Ingress"
            ING[Nginx Ingress Controller]
            CERT[Cert-Manager<br/>Let's Encrypt]
        end
        
        subgraph "Application Pods"
            POD1[App Pod 1<br/>Replicas: 3+]
            POD2[App Pod 2]
            POD3[App Pod 3]
        end
        
        subgraph "Worker Pods"
            WP1[Worker Pod 1<br/>Replicas: 2+]
            WP2[Worker Pod 2]
        end
        
        subgraph "Persistent Services"
            PG_HA[(PostgreSQL HA<br/>Primary + Replicas)]
            REDIS_CLUSTER[(Redis Cluster<br/>6+ nodes)]
        end
        
        subgraph "Monitoring"
            PROM[Prometheus]
            GRAF[Grafana]
            ALERT[Alertmanager]
        end
    end
    
    ING --> POD1
    ING --> POD2
    ING --> POD3
    CERT --> ING
    
    POD1 --> PG_HA
    POD2 --> PG_HA
    POD3 --> PG_HA
    
    POD1 --> REDIS_CLUSTER
    WP1 --> REDIS_CLUSTER
    
    PROM --> POD1
    PROM --> WP1
    GRAF --> PROM
    ALERT --> PROM
```

## Service Components

### 1. Web Application

**Technology**: PHP 8.4 + Laravel 12 + Filament 5

```mermaid
graph LR
    A[nginx:alpine] --> B[PHP-FPM 8.4]
    B --> C[Laravel App]
    C --> D[Filament UI]
    C --> E[API Routes]
    C --> F[Livewire Components]
```

**Configuration**:
- PHP-FPM with OPcache enabled
- nginx as reverse proxy
- Static asset compilation with Vite
- Redis for session storage and cache

**Scaling**: Horizontal scaling via replicas (stateless)

### 2. Queue Workers (Horizon)

**Purpose**: Process asynchronous jobs

```mermaid
graph TB
    A[Horizon Supervisor] --> B[Queue: default]
    A --> C[Queue: telemetry]
    A --> D[Queue: commands]
    A --> E[Queue: notifications]
    
    B --> F[Job Processing]
    C --> F
    D --> F
    E --> F
```

**Queue Configuration**:
- **default**: General background jobs
- **telemetry**: Telemetry processing (high priority)
- **commands**: Device command execution
- **notifications**: Email and event notifications

**Scaling**: Worker count per queue configurable

### 3. MQTT Subscriber

**Purpose**: Long-running process subscribing to device messages

```mermaid
stateDiagram-v2
    [*] --> Connect
    Connect --> Subscribe: Connection successful
    Subscribe --> Listen: Subscribed to topics
    Listen --> ProcessMessage: Message received
    ProcessMessage --> DispatchJob: Parse message
    DispatchJob --> Listen: Job queued
    Listen --> Reconnect: Connection lost
    Reconnect --> Connect
```

**Topics Subscribed**:
- `device/+/telemetry`
- `device/+/status`
- `device/+/error`
- `device/+/command/ack`

**Scaling**: Can run multiple subscribers with load balancing

### 4. WebSocket Server (Reverb)

**Purpose**: Real-time updates to UI

```mermaid
sequenceDiagram
    participant UI
    participant Reverb
    participant Redis
    participant Laravel
    
    UI->>Reverb: Connect WebSocket
    Reverb->>Reverb: Authenticate
    UI->>Reverb: Subscribe to channels
    
    Laravel->>Redis: Publish event
    Redis->>Reverb: Event notification
    Reverb->>UI: Broadcast event
```

**Channels**:
- Private: `organization.{org_id}.device.{device_uuid}`
- Private: `organization.{org_id}.telemetry`
- Presence: `organization.{org_id}.online`

**Scaling**: Multiple Reverb instances with Redis pub/sub

### 5. Scheduler

**Purpose**: Run scheduled tasks

**Scheduled Tasks**:
- Database cleanup (old logs)
- State reconciliation checks
- Analytics aggregation
- Report generation
- Backup jobs

**Implementation**: Single scheduler pod with leader election

## Data Storage

### PostgreSQL Configuration

```mermaid
graph TB
    subgraph "Primary Database"
        PRIMARY[(Primary<br/>Read/Write)]
    end
    
    subgraph "Read Replicas"
        REPLICA1[(Replica 1<br/>Read-only)]
        REPLICA2[(Replica 2<br/>Read-only)]
    end
    
    subgraph "Backup"
        BACKUP[(Backup Storage<br/>Point-in-time Recovery)]
    end
    
    PRIMARY -->|Streaming Replication| REPLICA1
    PRIMARY -->|Streaming Replication| REPLICA2
    PRIMARY -->|WAL Archiving| BACKUP
```

**Performance Optimizations**:
- JSONB indexes for flexible schema fields
- Partial indexes on active devices
- B-tree indexes on foreign keys
- Connection pooling via PgBouncer
- Read/write splitting in application

**Backup Strategy**:
- Continuous WAL archiving
- Daily full backups
- 30-day retention
- Point-in-time recovery capability

### Redis Configuration

```mermaid
graph TB
    subgraph "Redis Cluster"
        MASTER1[Master 1<br/>Shards 0-5460]
        MASTER2[Master 2<br/>Shards 5461-10922]
        MASTER3[Master 3<br/>Shards 10923-16383]
        
        SLAVE1[Replica 1]
        SLAVE2[Replica 2]
        SLAVE3[Replica 3]
    end
    
    MASTER1 --> SLAVE1
    MASTER2 --> SLAVE2
    MASTER3 --> SLAVE3
```

**Use Cases**:
- Queue backend (Laravel Horizon)
- Session storage
- Cache layer
- Rate limiting
- Pub/sub for broadcasting

**Configuration**:
- Persistence: AOF + RDB
- Memory policy: allkeys-lru for cache
- No eviction for queues

## Monitoring & Observability

```mermaid
graph TB
    subgraph "Application Metrics"
        APP[Laravel App] --> PROM_EXP[Prometheus Exporter]
        HORIZON[Horizon] --> PROM_EXP
    end
    
    subgraph "Infrastructure Metrics"
        PG[PostgreSQL] --> PG_EXP[Postgres Exporter]
        REDIS[Redis] --> REDIS_EXP[Redis Exporter]
        NGINX[nginx] --> NGINX_EXP[nginx Exporter]
    end
    
    subgraph "Monitoring Stack"
        PROM[Prometheus]
        GRAF[Grafana]
        ALERT[Alertmanager]
    end
    
    PROM_EXP --> PROM
    PG_EXP --> PROM
    REDIS_EXP --> PROM
    NGINX_EXP --> PROM
    
    PROM --> GRAF
    PROM --> ALERT
```

**Key Metrics**:
- Request rate and latency
- Queue depth and processing time
- Database connection pool usage
- Redis memory usage
- MQTT message rate
- Failed job rate

**Logging**:
- Structured JSON logs
- Centralized log aggregation (ELK/Loki)
- Log levels: DEBUG (dev), INFO (prod)
- Retention: 30 days

## Security

### Network Security

```mermaid
graph TB
    INTERNET[Internet] --> WAF[Web Application Firewall]
    WAF --> LB[Load Balancer<br/>SSL Termination]
    
    subgraph "DMZ"
        LB --> APP[Application Servers]
    end
    
    subgraph "Private Network"
        APP --> DB[(Databases)]
        APP --> CACHE[(Redis)]
        APP --> QUEUE[Queue Workers]
    end
    
    subgraph "Isolated Network"
        MQTT_BROKER[MQTT Broker]
    end
    
    IOT[IoT Devices] --> MQTT_BROKER
    APP --> MQTT_BROKER
```

**Security Measures**:
- SSL/TLS for all external communications
- Database accessible only from private network
- MQTT broker with authentication and ACLs
- Regular security updates
- Secrets management via Kubernetes secrets or Vault

### Application Security

- **CSRF Protection**: All state-changing requests
- **SQL Injection**: Eloquent ORM with parameterized queries
- **XSS Prevention**: Blade template escaping
- **Rate Limiting**: Login attempts, API requests
- **Input Validation**: Form requests and validators
- **Security Headers**: CSP, HSTS, X-Frame-Options

## Disaster Recovery

```mermaid
graph LR
    A[Production] -->|Continuous Backup| B[Backup Storage]
    B -->|Disaster Scenario| C[Recovery Process]
    C --> D{Recovery Type}
    D -->|Point-in-time| E[Restore to specific time]
    D -->|Latest| F[Restore latest backup]
    E --> G[Validate Data]
    F --> G
    G --> H[Resume Operations]
```

**RTO (Recovery Time Objective)**: < 4 hours
**RPO (Recovery Point Objective)**: < 15 minutes

**Backup Components**:
- Database dumps (full + incremental)
- Configuration files
- Uploaded files (device logos, etc.)
- Redis snapshots (optional)

## CI/CD Pipeline

```mermaid
graph LR
    A[Git Push] --> B[GitHub Actions]
    B --> C[Run Tests]
    C --> D{Tests Pass?}
    D -->|Yes| E[Build Image]
    D -->|No| F[Notify Developer]
    E --> G[Push to Registry]
    G --> H{Branch?}
    H -->|main| I[Deploy to Production]
    H -->|develop| J[Deploy to Staging]
    H -->|feature| K[Deploy to Preview]
```

**Pipeline Steps**:
1. Lint (PHPStan, Pint)
2. Unit Tests (Pest)
3. Feature Tests
4. Build Docker image
5. Security scan
6. Push to registry
7. Deploy to environment
8. Smoke tests
9. Notification

## Scaling Considerations

### Vertical Scaling
- Increase CPU/memory for database
- Larger instance types for workers

### Horizontal Scaling
- Add more web server replicas
- Add more queue worker pods
- Redis cluster sharding
- Database read replicas

### Auto-scaling Rules
- CPU utilization > 70%
- Queue depth > 1000 jobs
- Response time > 500ms (p95)
