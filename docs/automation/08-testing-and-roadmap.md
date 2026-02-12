# Automation Module - Testing and Roadmap

## Automated Coverage Snapshot

Current automated tests cover the major Phase 1 surfaces.

### Runtime and Matching

| Test File | Coverage |
|-----------|----------|
| `tests/Feature/Automation/DatabaseTriggerMatcherTest.php` | Compiled trigger matching behavior |
| `tests/Feature/Automation/TelemetryAutomationListenerTest.php` | Listener queue dispatch behavior and pipeline enable/disable gate |
| `tests/Feature/Automation/StartAutomationRunFromTelemetryExecutionTest.php` | End-to-end execution for condition pass/fail and command dispatch path |

### Validation and Compilation

| Test File | Coverage |
|-----------|----------|
| `tests/Unit/Automation/WorkflowNodeConfigValidatorTest.php` | Trigger/condition/command node config validation rules |
| `tests/Unit/Automation/WorkflowTelemetryTriggerCompilerTest.php` | Trigger compilation correctness, skipping invalid nodes, recompile replacement behavior |

### Filament Resource and DAG Page

| Test File | Coverage |
|-----------|----------|
| `tests/Filament/Admin/Resources/Automation/AutomationWorkflows/CreateAutomationWorkflowTest.php` | Create flow and redirect to DAG editor |
| `tests/Filament/Admin/Resources/Automation/AutomationWorkflows/ListAutomationWorkflowsTest.php` | Resource listing and DAG editor action exposure |
| `tests/Filament/Admin/Resources/Automation/AutomationWorkflows/EditAutomationDagTest.php` | DAG page rendering, options scope, save flow, trigger compilation, topology/config validation paths |

## Manual QA Scenarios

## 1) DAG Authoring

- Create workflow.
- Open DAG editor.
- Configure telemetry trigger, condition, command.
- Save DAG and reload page.
- Confirm summaries and configs persist.

## 2) Voltage-to-RED-Blink Scenario

- Telemetry trigger: energy meter voltage parameter.
- Condition: `trigger.value > threshold`.
- Command: RGB strip payload with RED blink semantics.
- Simulate telemetry above threshold.
- Confirm run completed with command step.
- Simulate telemetry below threshold.
- Confirm run completed without command step.

## 3) Dark Mode UX

- Switch Filament light/dark theme.
- Verify node readability, minimap, controls, modal contrast, and field usability.

## 4) Queue Path

- Ensure automation queue points to worker-consumed backend.
- Verify listener logs, run logs, and step logs appear in sequence.

## Quality Gates for Future Changes

Before merging automation runtime changes:

1. Topology validation tests must pass.
2. Node config validation tests must pass.
3. Trigger compiler tests must pass.
4. Runtime execution tests must pass.
5. Filament DAG page tests must pass for save/reload behavior.

## Current Known Limits

| Area | Current Limit |
|------|---------------|
| Trigger types | Runtime supports telemetry trigger only |
| Action types | Runtime supports command nodes only |
| Scheduling | Schedule trigger exists in graph palette but no runtime scheduler path yet |
| Delay/alert | Config persists, runtime execution not implemented |
| Multi-step advanced orchestration | Basic DFS traversal only, no advanced orchestration semantics |
| Generic node executors | Contract exists but pluggable executor chain not wired yet |

## Recommended Next Roadmap Phases

### Phase 2 - Runtime Breadth

- Implement schedule-trigger runtime path.
- Implement delay node runtime behavior.
- Implement alert node delivery channels.

### Phase 3 - Robust Execution Semantics

- Introduce pluggable node executors based on `NodeExecutor` contract.
- Add richer branch control semantics.
- Improve execution context propagation and typed node outputs.

### Phase 4 - Operational Maturity

- Dedicated automation queue and supervisor profiles by environment.
- Additional telemetry-to-run metrics and dashboards.
- Replay and dead-letter handling for edge-case recovery workflows.

## Definition of Done for Future Node Types

A node type should be considered production-ready only when all are present:

1. Modal configuration UX.
2. Server-side config validation.
3. Runtime executor behavior.
4. Run-step persistence semantics.
5. Observability logs with correlation context.
6. Feature and unit test coverage.
