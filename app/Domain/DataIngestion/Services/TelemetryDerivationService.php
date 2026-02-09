<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\Services;

use App\Domain\DeviceSchema\Models\DerivedParameterDefinition;
use Illuminate\Support\Collection;

class TelemetryDerivationService
{
    /**
     * @param  array<string, mixed>  $mutatedValues
     * @param  Collection<int, DerivedParameterDefinition>  $derivedParameters
     * @return array{derived_values: array<string, mixed>, final_values: array<string, mixed>}
     */
    public function derive(array $mutatedValues, Collection $derivedParameters): array
    {
        /** @var array<string, DerivedParameterDefinition> $pending */
        $pending = $derivedParameters->keyBy('key')->all();

        $resolved = $mutatedValues;
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

                $resolved[$key] = $value;
                $derivedValues[$key] = $value;

                unset($pending[$key]);
                $progress = true;
            }

            if (! $progress) {
                break;
            }

            $iterations++;
        }

        return [
            'derived_values' => $derivedValues,
            'final_values' => array_merge($mutatedValues, $derivedValues),
        ];
    }
}
