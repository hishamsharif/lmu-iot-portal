<?php

declare(strict_types=1);

namespace App\Domain\DeviceSchema\Services;

class JsonLogicEvaluator
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
            return array_map(fn (mixed $item) => $this->evaluate($item, $data), $expression);
        }

        if (count($expression) !== 1) {
            return $expression;
        }

        $operator = array_key_first($expression);
        $values = $expression[$operator];

        return $this->applyOperator((string) $operator, $values, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyOperator(string $operator, mixed $values, array $data): mixed
    {
        return match ($operator) {
            'var' => $this->resolveVar($values, $data),
            '+', '-', '*', '/', 'min', 'max' => $this->applyNumericOperator($operator, $values, $data),
            'if' => $this->applyIf($values, $data),
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

        if (! is_string($path) || $path === '') {
            return $default;
        }

        return data_get($data, $path, $default);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyNumericOperator(string $operator, mixed $values, array $data): mixed
    {
        $items = is_array($values) ? $values : [$values];

        $evaluated = array_map(fn (mixed $item) => $this->evaluate($item, $data), $items);
        $numbers = array_map([$this, 'toNumber'], $evaluated);

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
        $result = array_shift($numbers) ?? 0.0;

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
        $result = 1.0;

        foreach ($numbers as $number) {
            $result *= $number;
        }

        return $result;
    }

    /**
     * @param  array<int, float>  $numbers
     */
    private function divide(array $numbers): float
    {
        $result = array_shift($numbers) ?? 0.0;

        foreach ($numbers as $number) {
            if ($number == 0.0) {
                return $result;
            }

            $result /= $number;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyIf(mixed $values, array $data): mixed
    {
        $items = is_array($values) ? $values : [$values];
        $condition = $items[0] ?? null;
        $truthy = $items[1] ?? null;
        $falsy = $items[2] ?? null;

        return $this->evaluate($condition, $data) ? $this->evaluate($truthy, $data) : $this->evaluate($falsy, $data);
    }

    private function toNumber(mixed $value): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    /**
     * @param  array<mixed, mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
