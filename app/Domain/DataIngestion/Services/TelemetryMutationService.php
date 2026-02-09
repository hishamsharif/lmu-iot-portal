<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Services;

use App\Domain\DeviceSchema\Models\ParameterDefinition;
use Illuminate\Support\Collection;

class TelemetryMutationService
{
    /**
     * @param  array<string, mixed>  $extractedValues
     * @param  Collection<int, ParameterDefinition>  $parameters
     * @return array{mutated_values: array<string, mixed>, change_set: array<string, array{before: mixed, after: mixed}>}
     */
    public function mutate(array $extractedValues, Collection $parameters): array
    {
        $mutatedValues = [];
        $changeSet = [];

        foreach ($parameters as $parameter) {
            $before = $extractedValues[$parameter->key] ?? null;
            $after = $parameter->mutateValue($before);

            $mutatedValues[$parameter->key] = $after;
            $changeSet[$parameter->key] = [
                'before' => $before,
                'after' => $after,
            ];
        }

        return [
            'mutated_values' => $mutatedValues,
            'change_set' => $changeSet,
        ];
    }
}
