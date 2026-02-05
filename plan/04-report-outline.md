# Project Report Outline (Draft)

## 1. Introduction
- Problem statement (IoT device configuration + visualization platform)
- Scope (portal vs ingestion vs broker)
- Constraints (university timeframe, demo with 100 simulated devices)

## 2. Requirements
- Functional requirements (configuration, onboarding, dashboards, alerts, control)
- Non-functional requirements (multi-tenancy, scalability target, security basics)

## 3. Architecture
- System overview diagram (portal, broker, ingestion, DBs)
- Data flow: telemetry → validation → storage → visualization
- Technology choices (Laravel, Filament, Postgres, Timescale, MQTT, optional Go)

## 4. Database Design
- Terminology (DeviceType/Schema/Version/Parameter/Device)
- ERD (Phase 1 core): include `plan/01-erd-core.mmd`
- Design decisions:
  - Organization scoping
  - Versioning strategy
  - Latest readings snapshot table rationale
- Constraints and indexes summary

## 5. Implementation Plan & Project Management
- GitHub Projects workflow (columns, labels, milestones)
- User stories mapping to deliverables
- Testing strategy (Pest; model/relationship tests)

## 6. Evaluation (when implemented)
Evidence to capture during development:
- Screenshots of Filament CRUD screens (DeviceTypes/Schemas/Devices)
- Seeded devices list (100 simulated) and sample schema config
- DB measurements:
  - Row counts for core tables
  - Index usage (optional)
- Performance metrics (later phases):
  - Ingestion latency (publish → latest_readings updated)
  - Dashboard refresh times

## 7. Conclusion & Future Work
- What worked / lessons learned
- Future enhancements:
  - Rules engine expansion
  - Realtime via WebSockets/Reverb
  - Production-grade broker auth/ACL integration

