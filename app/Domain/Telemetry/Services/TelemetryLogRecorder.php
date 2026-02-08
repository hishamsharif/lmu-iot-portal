<?php

declare(strict_types=1);

namespace App\Domain\Telemetry\Services;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Telemetry\Enums\ValidationStatus;
use App\Domain\Telemetry\Models\DeviceTelemetryLog;
use App\Events\TelemetryReceived;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class TelemetryLogRecorder
{
    /**
     * Record a telemetry log entry for a device.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Device $device,
        array $payload,
        ?Carbon $recordedAt = null,
        ?Carbon $receivedAt = null,
        ?string $topicSuffix = null,
    ): DeviceTelemetryLog {
        $device->loadMissing('schemaVersion');

        $schemaVersion = $device->schemaVersion;

        if (! $schemaVersion instanceof DeviceSchemaVersion) {
            throw new RuntimeException('Device schema version is required to record telemetry logs.');
        }

        $topic = $this->resolveTopic($schemaVersion, $topicSuffix);

        $parameters = $topic instanceof SchemaVersionTopic
            ? $topic->parameters()
                ->where('is_active', true)
                ->orderBy('sequence')
                ->get()
            : $schemaVersion->parameters()
                ->where('parameter_definitions.is_active', true)
                ->orderBy('parameter_definitions.sequence')
                ->get();

        $derivedParameters = $schemaVersion->derivedParameters()->get();

        [$transformedValues, $validationStatus] = $this->evaluatePayload($payload, $parameters, $derivedParameters);

        $resolvedReceivedAt = $receivedAt ?? Carbon::now();
        $resolvedRecordedAt = $recordedAt ?? $resolvedReceivedAt;

        $log = DeviceTelemetryLog::create([
            'device_id' => $device->id,
            'device_schema_version_id' => $schemaVersion->id,
            'schema_version_topic_id' => $topic?->id,
            'raw_payload' => $payload,
            'transformed_values' => $transformedValues,
            'validation_status' => $validationStatus,
            'recorded_at' => $resolvedRecordedAt,
            'received_at' => $resolvedReceivedAt,
        ]);

        $log->loadMissing('device');

        event(new TelemetryReceived($log));

        return $log;
    }

    /**
     * Resolve the topic for the given schema version and suffix.
     */
    private function resolveTopic(DeviceSchemaVersion $schemaVersion, ?string $topicSuffix): ?SchemaVersionTopic
    {
        if ($topicSuffix === null) {
            return null;
        }

        return $schemaVersion->topics()
            ->where('suffix', $topicSuffix)
            ->where('direction', TopicDirection::Publish)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, ParameterDefinition>  $parameters
     * @param  Collection<int, DerivedParameterDefinition>  $derivedParameters
     * @return array{0: array<string, mixed>, 1: ValidationStatus}
     */
    private function evaluatePayload(array $payload, Collection $parameters, Collection $derivedParameters): array
    {
        $transformedValues = [];
        $hasInvalid = false;
        $hasCriticalInvalid = false;

        foreach ($parameters as $parameter) {
            $result = $parameter->evaluatePayload($payload);

            $transformedValues[$parameter->key] = $result['mutated'];

            if ($result['validation']['is_valid'] === false) {
                $hasInvalid = true;

                if ($result['validation']['is_critical'] === true) {
                    $hasCriticalInvalid = true;
                }
            }
        }

        $derivedValues = $this->evaluateDerivedParameters($derivedParameters, $transformedValues);

        $transformedValues = array_merge($transformedValues, $derivedValues);

        $status = match (true) {
            $hasCriticalInvalid => ValidationStatus::Invalid,
            $hasInvalid => ValidationStatus::Warning,
            default => ValidationStatus::Valid,
        };

        return [$transformedValues, $status];
    }

    /**
     * @param  Collection<int, DerivedParameterDefinition>  $derivedParameters
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function evaluateDerivedParameters(Collection $derivedParameters, array $inputs): array
    {
        $pending = $derivedParameters->keyBy('key')->all();
        $resolved = $inputs;
        $derivedValues = [];
        $maxIterations = count($pending);
        $iterations = 0;

        while ($pending !== [] && $iterations < $maxIterations) {
            $progress = false;

            foreach ($pending as $key => $definition) {
                $dependencies = $definition->resolvedDependencies();

                if (array_diff($dependencies, array_keys($resolved)) !== []) {
                    continue;
                }

                $value = $definition->evaluate($resolved);
                $derivedValues[$key] = $value;
                $resolved[$key] = $value;
                unset($pending[$key]);
                $progress = true;
            }

            if (! $progress) {
                break;
            }

            $iterations++;
        }

        return $derivedValues;
    }
}
