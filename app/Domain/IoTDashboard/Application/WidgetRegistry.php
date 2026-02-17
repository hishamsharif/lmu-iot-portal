<?php

declare(strict_types=1);

namespace App\Domain\IoTDashboard\Application;

use App\Domain\IoTDashboard\Contracts\WidgetDefinition;
use App\Domain\IoTDashboard\Enums\WidgetType;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use InvalidArgumentException;

class WidgetRegistry
{
    /**
     * @var array<string, WidgetDefinition>
     */
    private array $definitions = [];

    /**
     * @param  iterable<int, WidgetDefinition>  $definitions
     */
    public function __construct(iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->type()->value] = $definition;
        }
    }

    /**
     * @return array<int, WidgetDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function forType(WidgetType|string $type): WidgetDefinition
    {
        $resolvedType = $type instanceof WidgetType
            ? $type
            : WidgetType::tryFrom((string) $type);

        if (! $resolvedType instanceof WidgetType) {
            throw new InvalidArgumentException('Unsupported widget type.');
        }

        $definition = $this->definitions[$resolvedType->value] ?? null;

        if (! $definition instanceof WidgetDefinition) {
            throw new InvalidArgumentException("Missing widget definition for type [{$resolvedType->value}].");
        }

        return $definition;
    }

    public function forWidget(IoTDashboardWidget $widget): WidgetDefinition
    {
        return $this->forType($widget->widgetType());
    }
}
