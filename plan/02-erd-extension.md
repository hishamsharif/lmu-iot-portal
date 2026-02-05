# Phase 2+ ERD (Dashboards, Widgets, Rules) — Draft

This file documents the planned extension tables that come **after** Phase 1 core configuration schema is implemented and reviewed.

## Planned tables
- `widget_types`
- `device_type_widget_types` (pivot)
- `dashboards`
- `dashboard_widgets`
- `alert_rules`
- `alerts`
- `device_command_logs`

## Relationship sketch (high-level)
```mermaid
erDiagram
  DEVICE_TYPES ||--o{ DEVICE_TYPE_WIDGET_TYPES : allows
  WIDGET_TYPES ||--o{ DEVICE_TYPE_WIDGET_TYPES : allowed_for

  ORGANIZATIONS ||--o{ DASHBOARDS : owns
  DASHBOARDS ||--o{ DASHBOARD_WIDGETS : contains
  WIDGET_TYPES ||--o{ DASHBOARD_WIDGETS : instantiates
  DEVICES ||--o{ DASHBOARD_WIDGETS : targets

  ORGANIZATIONS ||--o{ ALERT_RULES : owns
  ALERT_RULES ||--o{ ALERTS : fires
  DEVICES ||--o{ ALERTS : affects
  DEVICES ||--o{ DEVICE_COMMAND_LOGS : receives
```

## Notes
- These tables are intentionally not part of Phase 1 migrations to keep the “schema foundation” small and correct.
- Once Phase 1 is merged, we can refine this diagram with concrete columns and constraints.

