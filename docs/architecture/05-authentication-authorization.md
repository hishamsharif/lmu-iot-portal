# Authentication & Authorization

## Overview

The platform implements a multi-tenant role-based access control (RBAC) system where users can belong to multiple organizations with different roles in each.

## Architecture

```mermaid
graph TB
    subgraph "Authentication Layer"
        A[Laravel Sanctum]
        B[Session Management]
        C[Password Hashing]
    end
    
    subgraph "Authorization Layer"
        D[Spatie Permissions]
        E[Custom Org Scoping]
        F[Filament Policies]
    end
    
    subgraph "Multi-Tenancy Layer"
        G[Organization Context]
        H[Tenant Middleware]
        I[Global Scopes]
    end
    
    A --> D
    B --> E
    D --> F
    E --> G
    F --> G
    G --> H
    H --> I
```

## User Authentication

### Authentication Flow

```mermaid
sequenceDiagram
    participant User
    participant Browser
    participant App as Laravel App
    participant Session
    participant DB as Database
    
    User->>Browser: Enter credentials
    Browser->>App: POST /login
    App->>DB: Lookup user by email
    DB->>App: Return user
    App->>App: Verify password hash
    
    alt Valid credentials
        App->>Session: Create session
        App->>DB: Update remember_token
        App->>Browser: Set session cookie
        Browser->>User: Redirect to dashboard
    else Invalid credentials
        App->>Browser: Return error
        Browser->>User: Show error message
    end
```

### User Model

```mermaid
classDiagram
    class User {
        +id: bigint
        +name: string
        +email: string
        +email_verified_at: timestamp
        +password: string (hashed)
        +is_super_admin: boolean
        +remember_token: string
        +organizations()
        +roles()
        +hasRole(role, organization)
        +assignRole(role, organization)
    }
    
    class Organization {
        +id: bigint
        +uuid: uuid
        +name: string
        +slug: string
        +users()
    }
    
    User "many" -- "many" Organization : belongs to
```

**Key Features**:
- Email-based authentication
- Bcrypt password hashing
- Session-based authentication (web)
- Super admin flag for platform administrators
- Email verification support

## Multi-Tenant Authorization

### Organization Context

```mermaid
graph TB
    A[User Logs In] --> B[Select Organization]
    B --> C[Set Active Organization]
    C --> D[Apply Tenant Scope]
    D --> E[All Queries Filtered]
    
    E --> F{Access Resource?}
    F -->|Same Org| G[Allow]
    F -->|Different Org| H[Deny]
```

### Tenant Middleware

```mermaid
sequenceDiagram
    participant Request
    participant Middleware
    participant Session
    participant Scope as Global Scope
    participant DB
    
    Request->>Middleware: HTTP Request
    Middleware->>Session: Get current organization
    
    alt Has organization context
        Session->>Middleware: Return organization
        Middleware->>Scope: Apply org filter
        Scope->>DB: WHERE organization_id = ?
        DB->>Request: Filtered results
    else No organization context
        Middleware->>Request: Redirect to org selector
    end
```

**Scoped Models**:
- Device
- DeviceType (organization-specific)
- Role
- DeviceCommandLog
- DeviceTelemetryLog
- DeviceDesiredState

**Global Models** (not scoped):
- User
- Organization
- DeviceType (global catalog)
- DeviceSchema
- DeviceSchemaVersion

### Organization Selection

```mermaid
stateDiagram-v2
    [*] --> LoginPage
    LoginPage --> OrgSelector: Successful login
    OrgSelector --> Dashboard: Select organization
    Dashboard --> OrgSelector: Switch organization
    Dashboard --> [*]: Logout
```

## Role-Based Access Control

### Permission System

Built on Spatie's Laravel-Permission with custom organization scoping:

```mermaid
graph TB
    subgraph "Super Admin"
        SA[Super Admin User]
        SA_PERM[All Permissions<br/>All Organizations]
    end
    
    subgraph "Organization Admin"
        OA[Org Admin User]
        OA_ROLE[Admin Role]
        OA_PERM[Org Management<br/>Device Management<br/>User Management]
    end
    
    subgraph "Device Operator"
        OP[Operator User]
        OP_ROLE[Operator Role]
        OP_PERM[View Devices<br/>Send Commands<br/>View Telemetry]
    end
    
    subgraph "Device Viewer"
        VW[Viewer User]
        VW_ROLE[Viewer Role]
        VW_PERM[View Devices<br/>View Telemetry]
    end
    
    SA --> SA_PERM
    OA --> OA_ROLE
    OA_ROLE --> OA_PERM
    OP --> OP_ROLE
    OP_ROLE --> OP_PERM
    VW --> VW_ROLE
    VW_ROLE --> VW_PERM
```

### Role Model

```mermaid
classDiagram
    class Role {
        +id: bigint
        +organization_id: bigint
        +name: string
        +guard_name: string
        +organization()
        +permissions()
        +users()
    }
    
    class Permission {
        +id: bigint
        +name: string
        +guard_name: string
        +roles()
    }
    
    class User {
        +id: bigint
        +name: string
        +is_super_admin: boolean
        +roles()
        +hasPermissionTo(permission, org)
    }
    
    Role "many" -- "many" Permission : has
    User "many" -- "many" Role : assigned
    Organization "1" -- "many" Role : scopes
```

### Permission Naming Convention

Permissions follow the pattern: `{action}_{resource}`

**Device Management**:
- `view_devices`
- `create_devices`
- `edit_devices`
- `delete_devices`
- `control_devices` (send commands)

**Device Type Management**:
- `view_device_types`
- `create_device_types`
- `edit_device_types`
- `delete_device_types`

**Schema Management**:
- `view_schemas`
- `create_schemas`
- `edit_schemas`
- `delete_schemas`

**User Management**:
- `view_users`
- `create_users`
- `edit_users`
- `delete_users`
- `assign_roles`

**Telemetry**:
- `view_telemetry`
- `export_telemetry`

### Authorization Flow

```mermaid
sequenceDiagram
    participant User
    participant UI as Filament UI
    participant Policy
    participant Gate as Authorization Gate
    participant DB
    
    User->>UI: Attempt action
    UI->>Policy: Check policy method
    Policy->>Gate: Check permissions
    
    alt Super Admin
        Gate->>UI: Allow
    else Has Permission
        Gate->>DB: Check user role + permission
        DB->>Gate: Permission found
        Gate->>UI: Allow
    else No Permission
        Gate->>DB: Check user role + permission
        DB->>Gate: Permission not found
        Gate->>UI: Deny
        UI->>User: Show 403 error
    end
```

### Filament Resource Policies

Each Filament resource uses a policy class:

```mermaid
graph LR
    A[DeviceResource] --> B[DevicePolicy]
    C[UserResource] --> D[UserPolicy]
    E[RoleResource] --> F[RolePolicy]
    
    B --> G[viewAny<br/>view<br/>create<br/>update<br/>delete]
    D --> G
    F --> G
```

**Policy Methods**:
- `viewAny()`: Can see the list view
- `view()`: Can view individual record
- `create()`: Can create new record
- `update()`: Can edit existing record
- `delete()`: Can delete record
- `restore()`: Can restore soft-deleted record
- `forceDelete()`: Can permanently delete record

## Super Admin Access

```mermaid
graph TB
    A[User Login] --> B{is_super_admin?}
    B -->|Yes| C[Bypass All Checks]
    B -->|No| D[Check Permissions]
    
    C --> E[Access All Organizations]
    C --> F[All Permissions]
    
    D --> G[Check Org Membership]
    G --> H{Member?}
    H -->|Yes| I[Check Role Permissions]
    H -->|No| J[Deny Access]
```

**Super Admin Privileges**:
- Access to all organizations
- Bypass all permission checks
- Manage global device type catalog
- View system-wide analytics
- Manage platform settings

## User-Organization Association

```mermaid
erDiagram
    USERS ||--o{ ORGANIZATION_USER : has
    ORGANIZATIONS ||--o{ ORGANIZATION_USER : has
    ORGANIZATIONS ||--o{ ROLES : defines
    USERS ||--o{ MODEL_HAS_ROLES : assigned
    ROLES ||--o{ MODEL_HAS_ROLES : assigned
    
    ORGANIZATION_USER {
        bigint organization_id FK
        bigint user_id FK
        timestamp created_at
    }
    
    MODEL_HAS_ROLES {
        bigint role_id FK
        bigint model_id FK
        string model_type
        bigint organization_id FK
    }
```

**Key Relationships**:
- Users can belong to multiple organizations
- Each user-organization pair can have multiple roles
- Roles are organization-scoped
- Permissions are checked within organization context

## Session Management

```mermaid
stateDiagram-v2
    [*] --> Unauthenticated
    Unauthenticated --> Authenticated: Login
    Authenticated --> OrgSelected: Select org
    OrgSelected --> OrgSelected: Perform actions
    OrgSelected --> OrgSwitch: Switch org
    OrgSwitch --> OrgSelected: New org context
    OrgSelected --> Authenticated: Clear org
    Authenticated --> Unauthenticated: Logout
    Unauthenticated --> [*]
```

**Session Data**:
```php
[
    'user_id' => 123,
    'current_organization_id' => 456,
    'remember_token' => 'abc...',
    'last_activity' => '2026-02-08 01:30:00',
]
```

## API Authentication (Future)

```mermaid
graph TB
    A[API Client] --> B[Personal Access Token]
    B --> C[Sanctum Token]
    C --> D[Authenticate Request]
    D --> E[Check Organization Scope]
    E --> F[Check Permissions]
    F --> G{Authorized?}
    G -->|Yes| H[Allow API Access]
    G -->|No| I[Return 401/403]
```

**Token Scopes** (planned):
- `devices:read`
- `devices:write`
- `telemetry:read`
- `commands:send`

## Security Best Practices

1. **Password Requirements**: Minimum 8 characters (configurable)
2. **Password Hashing**: Bcrypt with cost factor 10
3. **Session Security**: 
   - HTTP-only cookies
   - Secure flag in production
   - SameSite=Lax
4. **CSRF Protection**: Laravel's CSRF middleware on all state-changing operations
5. **Rate Limiting**: Login attempts limited to prevent brute force
6. **Two-Factor Authentication**: Planned for future release

## Audit Trail

All security-sensitive actions are logged:

```mermaid
graph LR
    A[User Action] --> B[Audit Log Entry]
    B --> C[Log Details]
    
    C --> D[User ID]
    C --> E[Organization ID]
    C --> F[Action Type]
    C --> G[Resource Type]
    C --> H[Resource ID]
    C --> I[IP Address]
    C --> J[Timestamp]
```

**Audited Actions**:
- Login/logout
- Organization switching
- Role assignments
- Permission changes
- Device commands
- Critical configuration changes
