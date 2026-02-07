<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Models;

use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Services\JsonLogicEvaluator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParameterDefinition extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\DeviceSchema\Models\ParameterDefinitionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ParameterDataType::class,
            'required' => 'bool',
            'is_critical' => 'bool',
            'is_active' => 'bool',
            'validation_rules' => 'array',
            'mutation_expression' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SchemaVersionTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(SchemaVersionTopic::class, 'schema_version_topic_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractValue(array $payload): mixed
    {
        $path = $this->normalizeJsonPath($this->json_path);

        if ($path === null) {
            return null;
        }

        return data_get($payload, $path);
    }

    public function mutateValue(mixed $value): mixed
    {
        $mutationExpression = $this->getAttribute('mutation_expression');

        if (! is_array($mutationExpression) || $mutationExpression === []) {
            return $value;
        }

        $evaluator = new JsonLogicEvaluator;

        return $evaluator->evaluate($mutationExpression, [
            'val' => $value,
        ]);
    }

    /**
     * @return array{is_valid: bool, error_code: string|null, is_critical: bool}
     */
    public function validateValue(mixed $value): array
    {
        if ($this->required && ($value === null || $value === '')) {
            return $this->invalidResult();
        }

        if ($value === null || $value === '') {
            return $this->validResult();
        }

        if (! $this->matchesDataType($value)) {
            return $this->invalidResult();
        }

        $rules = $this->getAttribute('validation_rules');

        if (! is_array($rules)) {
            $rules = [];
        }

        if (array_key_exists('min', $rules) && is_numeric($value)) {
            if ($value < $rules['min']) {
                return $this->invalidResult();
            }
        }

        if (array_key_exists('max', $rules) && is_numeric($value)) {
            if ($value > $rules['max']) {
                return $this->invalidResult();
            }
        }

        if (array_key_exists('regex', $rules) && is_string($value)) {
            if (! is_string($rules['regex']) || @preg_match($rules['regex'], '') === false) {
                return $this->invalidResult();
            }

            if (! preg_match($rules['regex'], $value)) {
                return $this->invalidResult();
            }
        }

        if (array_key_exists('enum', $rules) && is_array($rules['enum'])) {
            if (! in_array($value, $rules['enum'], true)) {
                return $this->invalidResult();
            }
        }

        return $this->validResult();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{value: mixed, mutated: mixed, validation: array{is_valid: bool, error_code: string|null, is_critical: bool}}
     */
    public function evaluatePayload(array $payload): array
    {
        $value = $this->extractValue($payload);
        $mutated = $this->mutateValue($value);
        $validation = $this->validateValue($mutated);

        return [
            'value' => $value,
            'mutated' => $mutated,
            'validation' => $validation,
        ];
    }

    private function normalizeJsonPath(string $path): ?string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            return null;
        }

        if ($normalized === '$') {
            return null;
        }

        if (str_starts_with($normalized, '$.')) {
            $normalized = substr($normalized, 2);
        }

        return $normalized;
    }

    private function matchesDataType(mixed $value): bool
    {
        $type = $this->getAttribute('type');

        if (! $type instanceof ParameterDataType) {
            if (! is_string($type) && ! is_int($type)) {
                return false;
            }

            $type = ParameterDataType::tryFrom((string) $type);

            if ($type === null) {
                return false;
            }
        }

        return match ($type) {
            ParameterDataType::Integer => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            ParameterDataType::Decimal => is_numeric($value),
            ParameterDataType::Boolean => is_bool($value) || in_array($value, ['true', 'false', 0, 1, '0', '1'], true),
            ParameterDataType::String => is_string($value),
            ParameterDataType::Json => is_array($value) || is_object($value),
        };
    }

    /**
     * @return array{is_valid: bool, error_code: string|null, is_critical: bool}
     */
    private function invalidResult(): array
    {
        return [
            'is_valid' => false,
            'error_code' => $this->validation_error_code,
            'is_critical' => $this->is_critical,
        ];
    }

    /**
     * @return array{is_valid: bool, error_code: string|null, is_critical: bool}
     */
    private function validResult(): array
    {
        return [
            'is_valid' => true,
            'error_code' => null,
            'is_critical' => false,
        ];
    }
}
