<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Services;

use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\Telemetry\Enums\ValidationStatus;
use Illuminate\Support\Collection;

class TelemetryValidationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, ParameterDefinition>  $parameters
     * @return array{
     *     extracted_values: array<string, mixed>,
     *     validation_errors: array<string, array{error_code: string|null, is_critical: bool}>,
     *     status: ValidationStatus,
     *     passes: bool
     * }
     */
    public function validate(array $payload, Collection $parameters): array
    {
        $extractedValues = [];
        $validationErrors = [];

        $hasInvalid = false;
        $hasCriticalInvalid = false;

        foreach ($parameters as $parameter) {
            $value = $parameter->extractValue($payload);
            $extractedValues[$parameter->key] = $value;

            $validation = $parameter->validateValue($value);

            if ($validation['is_valid'] === true) {
                continue;
            }

            $hasInvalid = true;
            $isCritical = $validation['is_critical'] === true;

            if ($isCritical) {
                $hasCriticalInvalid = true;
            }

            $validationErrors[$parameter->key] = [
                'error_code' => $validation['error_code'],
                'is_critical' => $isCritical,
            ];
        }

        $status = match (true) {
            $hasCriticalInvalid => ValidationStatus::Invalid,
            $hasInvalid => ValidationStatus::Warning,
            default => ValidationStatus::Valid,
        };

        return [
            'extracted_values' => $extractedValues,
            'validation_errors' => $validationErrors,
            'status' => $status,
            'passes' => $validationErrors === [],
        ];
    }
}
