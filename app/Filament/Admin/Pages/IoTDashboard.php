<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Models\IoTDashboard as IoTDashboardModel;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Filament\Admin\Resources\IoTDashboards\IoTDashboardResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IoTDashboard extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected Width|string|null $maxContentWidth = 'full';

    protected string $view = 'filament.admin.pages.io-t-dashboard';

    public ?int $dashboardId = null;

    /**
     * @var array<int, string>
     */
    private const array SERIES_COLOR_PALETTE = [
        '#22d3ee',
        '#a855f7',
        '#f97316',
        '#10b981',
        '#f43f5e',
        '#3b82f6',
        '#f59e0b',
        '#14b8a6',
    ];

    private const int GRID_STACK_CELL_HEIGHT = 96;

    private const int GRID_STACK_COLUMNS = 24;

    private const int LEGACY_GRID_STACK_COLUMNS = 4;

    private const int DEFAULT_WIDGET_GRID_COLUMNS = 6;

    public function mount(): void
    {
        $requestedDashboardId = request()->integer('dashboard');

        if (
            $requestedDashboardId > 0
            && IoTDashboardModel::query()->whereKey($requestedDashboardId)->exists()
        ) {
            $this->dashboardId = $requestedDashboardId;

            return;
        }

        $firstDashboardId = IoTDashboardModel::query()
            ->orderBy('name')
            ->value('id');

        $this->dashboardId = is_numeric($firstDashboardId)
            ? (int) $firstDashboardId
            : null;
    }

    public function getTitle(): string
    {
        return $this->selectedDashboard?->name ?? __('IoT Dashboard');
    }

    public function getSubheading(): ?string
    {
        $dashboard = $this->selectedDashboard;

        if (! $dashboard instanceof IoTDashboardModel) {
            return __('Pick a dashboard from Dashboards to start plotting telemetry.');
        }

        $organizationName = $dashboard->organization?->name ?? 'Unknown Organization';

        return __(
            ':organization · Realtime device telemetry with polling fallback.',
            ['organization' => $organizationName],
        );
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('dashboards')
                ->label('Dashboards')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->url(IoTDashboardResource::getUrl()),

            Action::make('addLineWidget')
                ->label('Add Line Widget')
                ->icon(Heroicon::OutlinedPresentationChartLine)
                ->visible(fn (): bool => $this->selectedDashboard instanceof IoTDashboardModel)
                ->slideOver()
                ->schema($this->lineWidgetFormSchema())
                ->action(function (array $data): void {
                    $dashboard = $this->selectedDashboard;

                    if (! $dashboard instanceof IoTDashboardModel) {
                        Notification::make()
                            ->title('Select a dashboard first')
                            ->warning()
                            ->send();

                        return;
                    }

                    $resolved = $this->resolveWidgetInput($dashboard, $data);

                    if ($resolved === null) {
                        return;
                    }

                    $nextSequence = ((int) $dashboard->widgets()->max('sequence')) + 1;

                    IoTDashboardWidget::query()->create([
                        'iot_dashboard_id' => $dashboard->id,
                        'device_id' => $resolved['device']->id,
                        'schema_version_topic_id' => $resolved['topic']->id,
                        'type' => 'line_chart',
                        'title' => trim((string) $data['title']),
                        'series_config' => $resolved['series_configuration'],
                        'options' => $this->buildWidgetOptions($data),
                        'use_websocket' => (bool) ($data['use_websocket'] ?? true),
                        'use_polling' => (bool) ($data['use_polling'] ?? true),
                        'polling_interval_seconds' => (int) ($data['polling_interval_seconds'] ?? 10),
                        'lookback_minutes' => (int) ($data['lookback_minutes'] ?? 120),
                        'max_points' => (int) ($data['max_points'] ?? 240),
                        'sequence' => $nextSequence,
                    ]);

                    Notification::make()
                        ->title('Line widget added')
                        ->body('The chart now listens to the selected device telemetry stream.')
                        ->success()
                        ->send();

                    $this->dispatchWidgetBootstrapEvent();
                }),
        ];
    }

    public function editWidgetAction(): Action
    {
        return Action::make('editWidget')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()
            ->color('gray')
            ->size('sm')
            ->slideOver()
            ->schema($this->lineWidgetFormSchema())
            ->fillForm(function (array $arguments): array {
                $widget = $this->resolveWidgetFromArguments($arguments);

                if (! $widget instanceof IoTDashboardWidget) {
                    return [];
                }

                $layout = $this->resolveWidgetLayout($widget);

                return [
                    'title' => $widget->title,
                    'device_id' => (string) $widget->device_id,
                    'schema_version_topic_id' => (string) $widget->schema_version_topic_id,
                    'parameter_keys' => collect($widget->resolvedSeriesConfig())
                        ->pluck('key')
                        ->values()
                        ->all(),
                    'use_websocket' => (bool) $widget->use_websocket,
                    'use_polling' => (bool) $widget->use_polling,
                    'polling_interval_seconds' => (int) $widget->polling_interval_seconds,
                    'lookback_minutes' => (int) $widget->lookback_minutes,
                    'max_points' => (int) $widget->max_points,
                    'grid_columns' => (string) $layout['w'],
                    'card_height_px' => $layout['h'] * self::GRID_STACK_CELL_HEIGHT,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $dashboard = $this->selectedDashboard;

                if (! $dashboard instanceof IoTDashboardModel) {
                    return;
                }

                $widget = $this->resolveWidgetFromArguments($arguments);

                if (! $widget instanceof IoTDashboardWidget) {
                    Notification::make()
                        ->title('Widget not found')
                        ->warning()
                        ->send();

                    return;
                }

                $resolved = $this->resolveWidgetInput($dashboard, $data);

                if ($resolved === null) {
                    return;
                }

                $widget->forceFill([
                    'device_id' => $resolved['device']->id,
                    'schema_version_topic_id' => $resolved['topic']->id,
                    'title' => trim((string) $data['title']),
                    'series_config' => $resolved['series_configuration'],
                    'options' => $this->buildWidgetOptions($data, $widget),
                    'use_websocket' => (bool) ($data['use_websocket'] ?? true),
                    'use_polling' => (bool) ($data['use_polling'] ?? true),
                    'polling_interval_seconds' => (int) ($data['polling_interval_seconds'] ?? 10),
                    'lookback_minutes' => (int) ($data['lookback_minutes'] ?? 120),
                    'max_points' => (int) ($data['max_points'] ?? 240),
                ])->save();

                Notification::make()
                    ->title('Widget updated')
                    ->success()
                    ->send();

                $this->dispatchWidgetBootstrapEvent();
            });
    }

    public function deleteWidget(int $widgetId): void
    {
        $dashboard = $this->selectedDashboard;

        if (! $dashboard instanceof IoTDashboardModel) {
            return;
        }

        $widget = $dashboard->widgets()->whereKey($widgetId)->first();

        if (! $widget instanceof IoTDashboardWidget) {
            return;
        }

        $widget->delete();

        Notification::make()
            ->title('Widget removed')
            ->success()
            ->send();

        $this->dispatchWidgetBootstrapEvent();
    }

    public function getSelectedDashboardProperty(): ?IoTDashboardModel
    {
        if (! is_numeric($this->dashboardId)) {
            return null;
        }

        return IoTDashboardModel::query()
            ->with([
                'organization:id,name',
                'widgets' => fn (HasMany $query) => $query
                    ->with([
                        'topic:id,label,suffix',
                        'device:id,uuid,name,external_id',
                    ])
                    ->orderBy('sequence')
                    ->orderBy('id'),
            ])
            ->find((int) $this->dashboardId);
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     type: string,
     *     title: string,
     *     topic: array{id: int|null, label: string|null, suffix: string|null},
     *     device: array{id: int|null, uuid: string|null, name: string|null},
     *     series: array<int, array{key: string, label: string, color: string}>,
     *     data_url: string,
     *     layout_url: string,
     *     use_websocket: bool,
     *     use_polling: bool,
     *     polling_interval_seconds: int,
     *     lookback_minutes: int,
     *     max_points: int,
     *     layout: array{x: int, y: int, w: int, h: int}
     * }
     */
    public function getWidgetBootstrapPayloadProperty(): array
    {
        $dashboard = $this->selectedDashboard;

        if (! $dashboard instanceof IoTDashboardModel) {
            return [];
        }

        return $dashboard->widgets
            ->map(function (IoTDashboardWidget $widget): array {
                return [
                    'id' => (int) $widget->id,
                    'type' => (string) $widget->type,
                    'title' => (string) $widget->title,
                    'topic' => [
                        'id' => is_numeric($widget->schema_version_topic_id) ? (int) $widget->schema_version_topic_id : null,
                        'label' => $widget->topic?->label,
                        'suffix' => $widget->topic?->suffix,
                    ],
                    'device' => [
                        'id' => is_numeric($widget->device_id) ? (int) $widget->device_id : null,
                        'uuid' => $widget->device?->uuid,
                        'name' => $widget->device?->name,
                    ],
                    'series' => $widget->resolvedSeriesConfig(),
                    'data_url' => route('admin.iot-dashboard.widgets.series', ['widget' => $widget]),
                    'layout_url' => route('admin.iot-dashboard.widgets.layout', ['widget' => $widget]),
                    'use_websocket' => (bool) $widget->use_websocket,
                    'use_polling' => (bool) $widget->use_polling,
                    'polling_interval_seconds' => (int) $widget->polling_interval_seconds,
                    'lookback_minutes' => (int) $widget->lookback_minutes,
                    'max_points' => (int) $widget->max_points,
                    'layout' => $this->resolveWidgetLayout($widget),
                ];
            })
            ->values()
            ->all();
    }

    private function lineWidgetFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Widget title')
                ->required()
                ->default('Line Chart')
                ->maxLength(255),
            Select::make('device_id')
                ->label('Device')
                ->options(fn (): array => $this->deviceOptionsForDashboard())
                ->helperText('Select a device first. Topic options are filtered by this device schema.')
                ->searchable()
                ->required()
                ->live(),
            Select::make('schema_version_topic_id')
                ->label('Publish topic')
                ->options(fn (Get $get): array => $this->topicOptionsForDevice($get('device_id')))
                ->searchable()
                ->required()
                ->live(),
            Select::make('parameter_keys')
                ->label('Series parameters')
                ->multiple()
                ->options(fn (Get $get): array => $this->parameterOptionsForTopic($get('schema_version_topic_id')))
                ->helperText('Choose one or more parameters. Colors are assigned automatically.')
                ->required(),
            Grid::make(2)
                ->schema([
                    Toggle::make('use_websocket')
                        ->label('Realtime WebSocket updates')
                        ->default(true),
                    Toggle::make('use_polling')
                        ->label('Polling fallback')
                        ->default(true)
                        ->live(),
                ]),
            Grid::make(3)
                ->schema([
                    TextInput::make('polling_interval_seconds')
                        ->label('Polling interval (s)')
                        ->integer()
                        ->minValue(2)
                        ->maxValue(300)
                        ->default(10)
                        ->visible(fn (Get $get): bool => (bool) $get('use_polling')),
                    TextInput::make('lookback_minutes')
                        ->label('Lookback window (min)')
                        ->integer()
                        ->minValue(5)
                        ->maxValue(1440)
                        ->default(120),
                    TextInput::make('max_points')
                        ->label('Max points')
                        ->integer()
                        ->minValue(20)
                        ->maxValue(1000)
                        ->default(240),
                ]),
            Grid::make(2)
                ->schema([
                    Select::make('grid_columns')
                        ->label('Widget width')
                        ->options(fn (): array => $this->gridColumnOptions())
                        ->default((string) self::DEFAULT_WIDGET_GRID_COLUMNS)
                        ->required(),
                    TextInput::make('card_height_px')
                        ->label('Initial height (px)')
                        ->integer()
                        ->minValue(260)
                        ->maxValue(900)
                        ->default(360)
                        ->required(),
                ]),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function deviceOptionsForDashboard(): array
    {
        $dashboard = $this->selectedDashboard;

        if (! $dashboard instanceof IoTDashboardModel) {
            return [];
        }

        return Device::query()
            ->where('organization_id', $dashboard->organization_id)
            ->orderBy('name')
            ->get(['id', 'name', 'external_id'])
            ->mapWithKeys(fn (Device $device): array => [
                (string) $device->id => is_string($device->external_id) && trim($device->external_id) !== ''
                    ? "{$device->name} ({$device->external_id})"
                    : $device->name,
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function topicOptionsForDevice(mixed $deviceId): array
    {
        $dashboard = $this->selectedDashboard;

        if (! $dashboard instanceof IoTDashboardModel || ! is_numeric($deviceId)) {
            return [];
        }

        $device = Device::query()
            ->whereKey((int) $deviceId)
            ->where('organization_id', $dashboard->organization_id)
            ->first(['id', 'device_schema_version_id']);

        if (! $device instanceof Device) {
            return [];
        }

        return SchemaVersionTopic::query()
            ->with('schemaVersion.schema.deviceType')
            ->where('direction', TopicDirection::Publish->value)
            ->where('device_schema_version_id', $device->device_schema_version_id)
            ->orderBy('label')
            ->get(['id', 'label', 'suffix', 'device_schema_version_id'])
            ->mapWithKeys(function (SchemaVersionTopic $topic): array {
                $schemaVersion = $topic->schemaVersion;
                $schemaName = $schemaVersion?->schema?->name ?? 'Unknown Schema';
                $version = $schemaVersion?->version ?? '?';
                $deviceType = $schemaVersion?->schema?->deviceType?->name ?? 'Unknown Type';

                return [
                    (string) $topic->id => "{$topic->label} ({$topic->suffix}) · {$deviceType} · {$schemaName} v{$version}",
                ];
            })
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function parameterOptionsForTopic(mixed $topicId): array
    {
        if (! is_numeric($topicId)) {
            return [];
        }

        return ParameterDefinition::query()
            ->where('schema_version_topic_id', (int) $topicId)
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get(['key', 'label'])
            ->mapWithKeys(fn (ParameterDefinition $parameter): array => [
                $parameter->key => "{$parameter->label} ({$parameter->key})",
            ])
            ->all();
    }

    private function resolveWidgetFromArguments(array $arguments): ?IoTDashboardWidget
    {
        $dashboard = $this->selectedDashboard;
        $widgetId = is_numeric($arguments['widget'] ?? null)
            ? (int) $arguments['widget']
            : null;

        if (! $dashboard instanceof IoTDashboardModel || $widgetId === null) {
            return null;
        }

        return IoTDashboardWidget::query()
            ->where('iot_dashboard_id', $dashboard->id)
            ->whereKey($widgetId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     device: Device,
     *     topic: SchemaVersionTopic,
     *     series_configuration: array<int, array{key: string, label: string, color: string}>
     * }|null
     */
    private function resolveWidgetInput(IoTDashboardModel $dashboard, array $data): ?array
    {
        $deviceId = is_numeric($data['device_id'] ?? null)
            ? (int) $data['device_id']
            : null;
        $topicId = is_numeric($data['schema_version_topic_id'] ?? null)
            ? (int) $data['schema_version_topic_id']
            : null;

        if ($deviceId === null || $topicId === null) {
            Notification::make()
                ->title('Device and topic are required')
                ->warning()
                ->send();

            return null;
        }

        $device = Device::query()
            ->whereKey($deviceId)
            ->where('organization_id', $dashboard->organization_id)
            ->first(['id', 'organization_id', 'device_schema_version_id']);

        if (! $device instanceof Device) {
            Notification::make()
                ->title('Invalid device selection')
                ->body('Choose a device that belongs to this dashboard organization.')
                ->warning()
                ->send();

            return null;
        }

        $topic = SchemaVersionTopic::query()
            ->whereKey($topicId)
            ->where('direction', TopicDirection::Publish->value)
            ->where('device_schema_version_id', $device->device_schema_version_id)
            ->first(['id', 'device_schema_version_id']);

        if (! $topic instanceof SchemaVersionTopic) {
            Notification::make()
                ->title('Invalid topic for device')
                ->body('Choose a publish topic that matches the selected device schema.')
                ->warning()
                ->send();

            return null;
        }

        $parameterOptions = $this->parameterOptionsForTopic($topicId);
        $seriesConfiguration = $this->buildSeriesConfiguration(
            parameterKeys: is_array($data['parameter_keys'] ?? null) ? $data['parameter_keys'] : [],
            parameterOptions: $parameterOptions,
        );

        if ($seriesConfiguration === []) {
            Notification::make()
                ->title('No series selected')
                ->body('Choose at least one parameter for the line chart.')
                ->warning()
                ->send();

            return null;
        }

        return [
            'device' => $device,
            'topic' => $topic,
            'series_configuration' => $seriesConfiguration,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     layout: array{x: int, y: int, w: int, h: int},
     *     layout_columns: int,
     *     grid_columns: int,
     *     card_height_px: int
     * }
     */
    private function buildWidgetOptions(array $data, ?IoTDashboardWidget $widget = null): array
    {
        $defaultLayout = $widget instanceof IoTDashboardWidget
            ? $this->resolveWidgetLayout($widget)
            : ['x' => 0, 'y' => 0, 'w' => self::DEFAULT_WIDGET_GRID_COLUMNS, 'h' => 4];
        $width = $this->sanitizeGridColumns($data['grid_columns'] ?? $defaultLayout['w']);
        $height = $this->gridRowsFromCardHeight(
            $data['card_height_px'] ?? ($defaultLayout['h'] * self::GRID_STACK_CELL_HEIGHT),
        );

        return [
            'layout' => [
                'x' => $defaultLayout['x'],
                'y' => $defaultLayout['y'],
                'w' => $width,
                'h' => $height,
            ],
            'layout_columns' => self::GRID_STACK_COLUMNS,
            'grid_columns' => $width,
            'card_height_px' => $height * self::GRID_STACK_CELL_HEIGHT,
        ];
    }

    /**
     * @param  array<int, mixed>  $parameterKeys
     * @param  array<int|string, string>  $parameterOptions
     * @return array<int, array{key: string, label: string, color: string}>
     */
    private function buildSeriesConfiguration(array $parameterKeys, array $parameterOptions): array
    {
        $series = [];
        $seen = [];

        foreach (array_values($parameterKeys) as $index => $key) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if (in_array($key, $seen, true)) {
                continue;
            }

            $seen[] = $key;

            $series[] = [
                'key' => $key,
                'label' => is_string($parameterOptions[$key] ?? null)
                    ? (string) $parameterOptions[$key]
                    : $key,
                'color' => self::SERIES_COLOR_PALETTE[$index % count(self::SERIES_COLOR_PALETTE)],
            ];
        }

        return $series;
    }

    private function sanitizeGridColumns(mixed $value): int
    {
        return (int) min(max((int) $value, 1), self::GRID_STACK_COLUMNS);
    }

    /**
     * @return array<int|string, string>
     */
    private function gridColumnOptions(): array
    {
        $options = [];

        foreach (range(1, self::GRID_STACK_COLUMNS) as $column) {
            $suffix = $column === 1 ? 'column' : 'columns';
            $options[(string) $column] = "{$column} {$suffix}";
        }

        return $options;
    }

    private function sanitizeGridRows(mixed $value): int
    {
        return (int) min(max((int) $value, 2), 12);
    }

    private function sanitizeGridCoordinate(mixed $value): int
    {
        return (int) max((int) $value, 0);
    }

    private function sanitizeCardHeight(mixed $value): int
    {
        return (int) min(max((int) $value, 260), 900);
    }

    private function gridRowsFromCardHeight(mixed $cardHeight): int
    {
        $heightPx = $this->sanitizeCardHeight($cardHeight);

        return $this->sanitizeGridRows((int) round($heightPx / self::GRID_STACK_CELL_HEIGHT));
    }

    /**
     * @return array{x: int, y: int, w: int, h: int}
     */
    private function resolveWidgetLayout(IoTDashboardWidget $widget): array
    {
        $options = is_array($widget->options) ? $widget->options : [];
        $layout = data_get($options, 'layout', []);
        $fallbackW = $this->sanitizeGridColumns(data_get($options, 'grid_columns', self::DEFAULT_WIDGET_GRID_COLUMNS));
        $fallbackH = $this->gridRowsFromCardHeight(data_get($options, 'card_height_px', 360));
        $layoutColumns = max(
            1,
            (int) data_get($options, 'layout_columns', self::LEGACY_GRID_STACK_COLUMNS),
        );
        $scaleFactor = self::GRID_STACK_COLUMNS / $layoutColumns;

        $x = (int) data_get($layout, 'x', 0);
        $w = (int) data_get($layout, 'w', $fallbackW);

        if ($layoutColumns !== self::GRID_STACK_COLUMNS) {
            $x = (int) round($x * $scaleFactor);
            $w = (int) round($w * $scaleFactor);
        }

        return [
            'x' => $this->sanitizeGridCoordinate($x),
            'y' => $this->sanitizeGridCoordinate(data_get($layout, 'y', 0)),
            'w' => $this->sanitizeGridColumns($w),
            'h' => $this->sanitizeGridRows(data_get($layout, 'h', $fallbackH)),
        ];
    }

    private function dispatchWidgetBootstrapEvent(): void
    {
        $this->dispatch('iot-dashboard-widgets-updated', widgets: $this->widgetBootstrapPayload);
    }
}
