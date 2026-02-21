<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

class AutomationJsonLogicEvaluator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function evaluate(mixed $expression, array $data = []): mixed
    {
        if (! is_array($expression)) {
            return $expression;
        }

        if (! $this->isAssoc($expression)) {
            return array_map(fn (mixed $item): mixed => $this->evaluate($item, $data), $expression);
        }

        if (count($expression) !== 1) {
            return $expression;
        }

        $operator = array_key_first($expression);

        return $this->applyOperator((string) $operator, $expression[$operator], $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyOperator(string $operator, mixed $values, array $data): mixed
    {
        return match ($operator) {
            'var' => $this->resolveVar($values, $data),
            '+', '-', '*', '/', 'min', 'max' => $this->applyNumericOperator($operator, $values, $data),
            '==', '===', '!=', '!==', '>', '>=', '<', '<=' => $this->applyComparisonOperator($operator, $values, $data),
            'and', 'or', '!', '!!' => $this->applyLogicalOperator($operator, $values, $data),
            'if' => $this->applyIf($values, $data),
            'missing' => $this->applyMissing($values, $data),
            'missing_some' => $this->applyMissingSome($values, $data),
            default => $values,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveVar(mixed $values, array $data): mixed
    {
        if (is_array($values)) {
            $path = $values[0] ?? null;
            $default = $values[1] ?? null;
        } else {
            $path = $values;
            $default = null;
        }

        if ($path === '') {
            return $data;
        }

        if (! is_string($path)) {
            return $default;
        }

        if (! $this->pathExists($data, $path)) {
            return $default;
        }

        return data_get($data, $path);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyNumericOperator(string $operator, mixed $values, array $data): mixed
    {
        $items = is_array($values) ? array_values($values) : [$values];
        $numbers = array_map(
            fn (mixed $item): float => $this->toNumber($this->evaluate($item, $data)),
            $items,
        );

        return match ($operator) {
            '+' => array_sum($numbers),
            '-' => $this->subtract($numbers),
            '*' => $this->multiply($numbers),
            '/' => $this->divide($numbers),
            'min' => $numbers === [] ? null : min($numbers),
            'max' => $numbers === [] ? null : max($numbers),
            default => null,
        };
    }

    /**
     * @param  array<int, float>  $numbers
     */
    private function subtract(array $numbers): float
    {
        if ($numbers === []) {
            return 0.0;
        }

        if (count($numbers) === 1) {
            return -$numbers[0];
        }

        $result = (float) array_shift($numbers);

        foreach ($numbers as $number) {
            $result -= $number;
        }

        return $result;
    }

    /**
     * @param  array<int, float>  $numbers
     */
    private function multiply(array $numbers): float
    {
        if ($numbers === []) {
            return 0.0;
        }

        $result = 1.0;

        foreach ($numbers as $number) {
            $result *= $number;
        }

        return $result;
    }

    /**
     * @param  array<int, float>  $numbers
     */
    private function divide(array $numbers): ?float
    {
        if ($numbers === []) {
            return 0.0;
        }

        $result = (float) array_shift($numbers);

        foreach ($numbers as $number) {
            if ($number == 0.0) {
                return null;
            }

            $result /= $number;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyComparisonOperator(string $operator, mixed $values, array $data): bool
    {
        $items = is_array($values) ? array_values($values) : [$values];
        if (count($items) < 2) {
            return false;
        }

        $evaluated = array_map(fn (mixed $item): mixed => $this->evaluate($item, $data), $items);

        if ($operator === '==') {
            return $this->allPairsMatch($evaluated, fn (mixed $left, mixed $right): bool => $left == $right);
        }

        if ($operator === '===') {
            return $this->allPairsMatch($evaluated, fn (mixed $left, mixed $right): bool => $left === $right);
        }

        if ($operator === '!=') {
            return $this->allPairsMatch($evaluated, fn (mixed $left, mixed $right): bool => $left != $right);
        }

        if ($operator === '!==') {
            return $this->allPairsMatch($evaluated, fn (mixed $left, mixed $right): bool => $left !== $right);
        }

        return $this->allPairsMatch($evaluated, function (mixed $left, mixed $right) use ($operator): bool {
            return match ($operator) {
                '>' => $this->compareValues($left, $right) > 0,
                '>=' => $this->compareValues($left, $right) >= 0,
                '<' => $this->compareValues($left, $right) < 0,
                '<=' => $this->compareValues($left, $right) <= 0,
                default => false,
            };
        });
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function allPairsMatch(array $values, callable $comparator): bool
    {
        $count = count($values);

        for ($index = 0; $index < $count - 1; $index++) {
            if (! $comparator($values[$index], $values[$index + 1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyLogicalOperator(string $operator, mixed $values, array $data): mixed
    {
        $items = is_array($values) ? array_values($values) : [$values];

        if ($operator === '!') {
            return ! $this->isTruthy($this->evaluate($items[0] ?? null, $data));
        }

        if ($operator === '!!') {
            return $this->isTruthy($this->evaluate($items[0] ?? null, $data));
        }

        if ($operator === 'and') {
            $last = null;

            foreach ($items as $item) {
                $last = $this->evaluate($item, $data);

                if (! $this->isTruthy($last)) {
                    return $last;
                }
            }

            return $last;
        }

        if ($operator === 'or') {
            $last = null;

            foreach ($items as $item) {
                $last = $this->evaluate($item, $data);

                if ($this->isTruthy($last)) {
                    return $last;
                }
            }

            return $last;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyIf(mixed $values, array $data): mixed
    {
        $items = is_array($values) ? array_values($values) : [$values];
        $count = count($items);

        for ($index = 0; $index + 1 < $count; $index += 2) {
            if ($this->isTruthy($this->evaluate($items[$index], $data))) {
                return $this->evaluate($items[$index + 1], $data);
            }
        }

        if ($count % 2 === 1) {
            return $this->evaluate($items[$count - 1], $data);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function applyMissing(mixed $values, array $data): array
    {
        $paths = $this->normalizePathList($values);

        $missing = array_filter($paths, fn (string $path): bool => ! $this->pathExists($data, $path));

        return array_values($missing);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function applyMissingSome(mixed $values, array $data): array
    {
        if (! is_array($values)) {
            return [];
        }

        $minimumRequired = max(0, $this->resolveInteger($values[0] ?? null) ?? 0);
        $paths = $this->normalizePathList($values[1] ?? []);

        if ($paths === []) {
            return [];
        }

        $missing = $this->applyMissing($paths, $data);
        $presentCount = count($paths) - count($missing);

        if ($presentCount >= $minimumRequired) {
            return [];
        }

        return $missing;
    }

    private function toNumber(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if ($value === null) {
            return 0.0;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || ! is_numeric($trimmed)) {
                return 0.0;
            }

            return (float) $trimmed;
        }

        if (is_array($value)) {
            if ($value === []) {
                return 0.0;
            }

            if (count($value) === 1) {
                return $this->toNumber(array_values($value)[0]);
            }
        }

        return 0.0;
    }

    private function compareValues(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        $leftString = is_scalar($left) || $left instanceof \Stringable ? (string) $left : '';
        $rightString = is_scalar($right) || $right instanceof \Stringable ? (string) $right : '';

        return $leftString <=> $rightString;
    }

    private function resolveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return null;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }

        if (is_string($value)) {
            return $value !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function normalizePathList(mixed $values): array
    {
        $candidates = [];

        if (is_array($values)) {
            $candidates = $values;
        } elseif (is_string($values)) {
            $candidates = [$values];
        }

        $paths = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $path = trim($candidate);
            if ($path === '' || in_array($path, $paths, true)) {
                continue;
            }

            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pathExists(array $data, string $path): bool
    {
        if ($path === '') {
            return true;
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current)) {
                if (array_key_exists($segment, $current)) {
                    $current = $current[$segment];

                    continue;
                }

                if (ctype_digit($segment)) {
                    $numericSegment = (int) $segment;

                    if (array_key_exists($numericSegment, $current)) {
                        $current = $current[$numericSegment];

                        continue;
                    }
                }

                return false;
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
