<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\IoTDashboard\Enums\BarInterval;
use App\Domain\IoTDashboard\Enums\GaugeStyle;
use App\Domain\IoTDashboard\Models\IoTDashboard as IoTDashboardModel;
use App\Domain\IoTDashboard\Models\IoTDashboardWidget;
use App\Filament\Admin\Resources\IoTDashboards\IoTDashboardResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read IoTDashboardModel|null $selectedDashboard
 * @property-read array<int, array<string, mixed>> $widgetBootstrapPayload
 */
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

    private const string WIDGET_TYPE_LINE_CHART = 'line_chart';

    private const string WIDGET_TYPE_BAR_CHART = 'bar_chart';

    private const string WIDGET_TYPE_GAUGE_CHART = 'gauge_chart';

    public function mount(): void
    {
        $requestedDashboardId = request()->integer('dashboard');

        if ($requestedDashboardId > 0) {
            $dashboardExists = $this->getDashboardQuery()
                ->whereKey($requestedDashboardId)
                ->exists();

            if ($dashboardExists) {
                $this->dashboardId = $requestedDashboardId;

                return;
            }
        }

        $firstDashboardId = $this->getDashboardQuery()
            ->orderBy('name')
            ->value('id');

        $this->dashboardId = is_numeric($firstDashboardId)
            ? (int) $firstDashboardId
            : null;
    }

    public function getTitle(): string
    {
        $dashboard = $this->selectedDashboard();

        if (! $dashboard instanceof IoTDashboardModel) {
            return __('IoT Dashboard');
        }

        return $dashboard->name;
    }

    public function getSubheading(): ?string
    {
        $dashboard = $this->selectedDashboard();

        if (! $dashboard instanceof IoTDashboardModel) {
            return __('Pick a dashboard from Dashboards to start plotting telemetry.');
        }

        $organization = $dashboard->organization;
        $organizationName = is_string($organization?->name) && trim($organization->name) !== ''
            ? $organization->name
            : 'Unknown Organization';

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

            ActionGroup::make([
                Action::make('addLineWidget')
                    ->label('Add Line Widget')
                    ->icon(Heroicon::OutlinedPresentationChartLine)
                    ->visible(fn (): bool => $this->selectedDashboard() instanceof IoTDashboardModel)
                    ->slideOver()
                    ->schema($this->lineWidgetFormSchema())
                    ->action(function (array $data): void {
                        $dashboard = $this->selectedDashboard();

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

                        $maxSequence = $dashboard->widgets()->max('sequence');
                        $nextSequence = (is_numeric($maxSequence) ? (int) $maxSequence : 0) + 1;

                        IoTDashboardWidget::query()->create([
                            'iot_dashboard_id' => $dashboard->id,
                            'device_id' => $resolved['device']->id,
                            'schema_version_topic_id' => $resolved['topic']->id,
                            'type' => self::WIDGET_TYPE_LINE_CHART,
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

                        $this->refreshDashboardComputedProperties();
                        $this->dispatchWidgetBootstrapEvent();
                    }),

                Action::make('addBarWidget')
                    ->label('Add Bar Widget')
                    ->icon(Heroicon::OutlinedPresentationChartLine)
                    ->visible(fn (): bool => $this->selectedDashboard() instanceof IoTDashboardModel)
                    ->slideOver()
                    ->schema($this->barWidgetFormSchema())
                    ->action(function (array $data): void {
                        $dashboard = $this->selectedDashboard();

                        if (! $dashboard instanceof IoTDashboardModel) {
                            Notification::make()
                                ->title('Select a dashboard first')
                                ->warning()
                                ->send();

                            return;
                        }

                        $parameterKey = is_string($data['parameter_key'] ?? null)
                            ? (string) $data['parameter_key']
                            : null;
                        $resolved = $this->resolveWidgetInput($dashboard, [
                            ...$data,
                            'parameter_keys' => $parameterKey === null ? [] : [$parameterKey],
                        ]);

                        if ($resolved === null) {
                            return;
                        }

                        $maxSequence = $dashboard->widgets()->max('sequence');
                        $nextSequence = (is_numeric($maxSequence) ? (int) $maxSequence : 0) + 1;

                        IoTDashboardWidget::query()->create([
                            'iot_dashboard_id' => $dashboard->id,
                            'device_id' => $resolved['device']->id,
                            'schema_version_topic_id' => $resolved['topic']->id,
                            'type' => self::WIDGET_TYPE_BAR_CHART,
                            'title' => trim((string) $data['title']),
                            'series_config' => $resolved['series_configuration'],
                            'options' => $this->buildWidgetOptions([
                                ...$data,
                                'bar_interval' => $this->sanitizeBarInterval($data['bar_interval'] ?? BarInterval::Hourly->value)->value,
                            ]),
                            'use_websocket' => (bool) ($data['use_websocket'] ?? false),
                            'use_polling' => (bool) ($data['use_polling'] ?? true),
                            'polling_interval_seconds' => (int) ($data['polling_interval_seconds'] ?? 60),
                            'lookback_minutes' => (int) ($data['lookback_minutes'] ?? 43200),
                            'max_points' => (int) ($data['max_points'] ?? 31),
                            'sequence' => $nextSequence,
                        ]);

                        Notification::make()
                            ->title('Bar widget added')
                            ->body('The chart now aggregates energy consumption by hour/day.')
                            ->success()
                            ->send();

                        $this->refreshDashboardComputedProperties();
                        $this->dispatchWidgetBootstrapEvent();
                    }),

                Action::make('addGaugeWidget')
                    ->label('Add Gauge Widget')
                    ->icon(Heroicon::OutlinedPresentationChartLine)
                    ->visible(fn (): bool => $this->selectedDashboard() instanceof IoTDashboardModel)
                    ->slideOver()
                    ->schema($this->gaugeWidgetFormSchema())
                    ->action(function (array $data): void {
                        $dashboard = $this->selectedDashboard();

                        if (! $dashboard instanceof IoTDashboardModel) {
                            Notification::make()
                                ->title('Select a dashboard first')
                                ->warning()
                                ->send();

                            return;
                        }

                        $parameterKey = is_string($data['parameter_key'] ?? null)
                            ? (string) $data['parameter_key']
                            : null;
                        $resolved = $this->resolveWidgetInput($dashboard, [
                            ...$data,
                            'parameter_keys' => $parameterKey === null ? [] : [$parameterKey],
                        ]);

                        if ($resolved === null) {
                            return;
                        }

                        $maxSequence = $dashboard->widgets()->max('sequence');
                        $nextSequence = (is_numeric($maxSequence) ? (int) $maxSequence : 0) + 1;

                        IoTDashboardWidget::query()->create([
                            'iot_dashboard_id' => $dashboard->id,
                            'device_id' => $resolved['device']->id,
                            'schema_version_topic_id' => $resolved['topic']->id,
                            'type' => self::WIDGET_TYPE_GAUGE_CHART,
                            'title' => trim((string) $data['title']),
                            'series_config' => $resolved['series_configuration'],
                            'options' => $this->buildWidgetOptions($data),
                            'use_websocket' => (bool) ($data['use_websocket'] ?? true),
                            'use_polling' => (bool) ($data['use_polling'] ?? true),
                            'polling_interval_seconds' => (int) ($data['polling_interval_seconds'] ?? 10),
                            'lookback_minutes' => (int) ($data['lookback_minutes'] ?? 180),
                            'max_points' => 1,
                            'sequence' => $nextSequence,
                        ]);

                        Notification::make()
                            ->title('Gauge widget added')
                            ->body('The gauge now displays a live numeric telemetry value.')
                            ->success()
                            ->send();

                        $this->refreshDashboardComputedProperties();
                        $this->dispatchWidgetBootstrapEvent();
                    }),
            ])
                ->label('Add Widget')
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => $this->selectedDashboard() instanceof IoTDashboardModel),
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
                $dashboard = $this->selectedDashboard();

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

                $this->refreshDashboardComputedProperties();
                $this->dispatchWidgetBootstrapEvent();
            });
    }

    public function deleteWidgetAction(): Action
    {
        return Action::make('deleteWidget')
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Delete widget')
            ->modalDescription('This will remove the widget from the dashboard.')
            ->modalSubmitActionLabel('Delete widget')
            ->action(function (array $arguments): void {
                $dashboard = $this->selectedDashboard();

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

                $widget->delete();

                Notification::make()
                    ->title('Widget removed')
                    ->success()
                    ->send();

                $this->refreshDashboardComputedProperties();
                $this->dispatchWidgetBootstrapEvent();
            });
    }

    public function getSelectedDashboardProperty(): ?IoTDashboardModel
    {
        if (! is_numeric($this->dashboardId)) {
            return null;
        }

        $dashboard = $this->getDashboardQuery()
            ->with([
                'organization:id,name',
                'widgets' => fn ($query) => $query
                    ->with([
                        'topic:id,label,suffix',
                        'device:id,uuid,name,external_id',
                    ])
                    ->orderBy('sequence')
                    ->orderBy('id'),
            ])
            ->find((int) $this->dashboardId);

        return $dashboard instanceof IoTDashboardModel ? $dashboard : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Domain\IoTDashboard\Models\IoTDashboard>
     */
    private function getDashboardQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = IoTDashboardModel::query();

        /** @var \App\Domain\Shared\Models\User $user */
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('organization_id', $user->organizations()->pluck('id'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWidgetBootstrapPayloadProperty(): array
    {
        $dashboard = $this->selectedDashboard();

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
                        'id' => (int) $widget->schema_version_topic_id,
                        'label' => $widget->topic?->label,
                        'suffix' => $widget->topic?->suffix,
                    ],
                    'device' => [
                        'id' => $widget->device_id === null ? null : (int) $widget->device_id,
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
                    'bar_interval' => $this->resolveWidgetBarInterval($widget)->value,
                    'gauge_style' => $this->resolveWidgetGaugeStyle($widget)->value,
                    'gauge_min' => $this->resolveWidgetGaugeMinimum($widget),
                    'gauge_max' => $this->resolveWidgetGaugeMaximum($widget),
                    'gauge_ranges' => $this->resolveWidgetGaugeRanges($widget),
                    'layout' => $this->resolveWidgetLayout($widget),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, \Filament\Actions\Action|\Filament\Actions\ActionGroup|\Filament\Schemas\Components\Component>
     */
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
     * @return array<int, \Filament\Actions\Action|\Filament\Actions\ActionGroup|\Filament\Schemas\Components\Component>
     */
    private function barWidgetFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Widget title')
                ->required()
                ->default('Energy Consumption')
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
            Select::make('parameter_key')
                ->label('Counter parameter')
                ->options(fn (Get $get): array => $this->counterParameterOptionsForTopic($get('schema_version_topic_id')))
                ->helperText('Choose the cumulative energy counter parameter (for example, total_energy_kwh).')
                ->required(),
            Select::make('bar_interval')
                ->label('Aggregation interval')
                ->options(BarInterval::class)
                ->default(BarInterval::Hourly->value)
                ->required(),
            Grid::make(2)
                ->schema([
                    Toggle::make('use_websocket')
                        ->label('Realtime WebSocket updates')
                        ->default(false),
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
                        ->minValue(5)
                        ->maxValue(300)
                        ->default(60)
                        ->visible(fn (Get $get): bool => (bool) $get('use_polling')),
                    TextInput::make('lookback_minutes')
                        ->label('Lookback window (min)')
                        ->integer()
                        ->minValue(60)
                        ->maxValue(129600)
                        ->default(43200),
                    TextInput::make('max_points')
                        ->label('Max bars')
                        ->integer()
                        ->minValue(6)
                        ->maxValue(1000)
                        ->default(31),
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
     * @return array<int, \Filament\Actions\Action|\Filament\Actions\ActionGroup|\Filament\Schemas\Components\Component>
     */
    private function gaugeWidgetFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Widget title')
                ->required()
                ->default('Gauge')
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
            Select::make('parameter_key')
                ->label('Gauge parameter')
                ->options(fn (Get $get): array => $this->numericParameterOptionsForTopic($get('schema_version_topic_id')))
                ->helperText('Choose a numeric parameter to display.')
                ->required(),
            Select::make('gauge_style')
                ->label('Gauge style')
                ->options(GaugeStyle::class)
                ->default(GaugeStyle::Classic->value)
                ->required(),
            Grid::make(2)
                ->schema([
                    TextInput::make('gauge_min')
                        ->label('Minimum')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    TextInput::make('gauge_max')
                        ->label('Maximum')
                        ->numeric()
                        ->default(100)
                        ->required(),
                ]),
            Repeater::make('gauge_ranges')
                ->label('Color ranges')
                ->default($this->defaultGaugeRanges())
                ->minItems(1)
                ->maxItems(10)
                ->reorderable()
                ->schema([
                    TextInput::make('from')
                        ->label('From')
                        ->numeric()
                        ->required(),
                    TextInput::make('to')
                        ->label('To')
                        ->numeric()
                        ->required(),
                    ColorPicker::make('color')
                        ->label('Color')
                        ->required(),
                ])
                ->columns(3)
                ->columnSpanFull(),
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
                        ->default(180),
                    TextInput::make('max_points')
                        ->label('Max points')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(10)
                        ->default(1),
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
        $dashboard = $this->selectedDashboard();

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
        $dashboard = $this->selectedDashboard();

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
                $schemaNameValue = data_get($topic, 'schemaVersion.schema.name');
                $schemaName = is_string($schemaNameValue) && trim($schemaNameValue) !== ''
                    ? $schemaNameValue
                    : 'Unknown Schema';
                $versionValue = data_get($topic, 'schemaVersion.version');
                $version = is_scalar($versionValue)
                    ? (string) $versionValue
                    : '?';
                $deviceTypeValue = data_get($topic, 'schemaVersion.schema.deviceType.name');
                $deviceType = is_string($deviceTypeValue) && trim($deviceTypeValue) !== ''
                    ? $deviceTypeValue
                    : 'Unknown Type';

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

    /**
     * @return array<int|string, string>
     */
    private function counterParameterOptionsForTopic(mixed $topicId): array
    {
        if (! is_numeric($topicId)) {
            return [];
        }

        $query = ParameterDefinition::query()
            ->where('schema_version_topic_id', (int) $topicId)
            ->where('is_active', true)
            ->whereIn('type', [ParameterDataType::Integer->value, ParameterDataType::Decimal->value])
            ->where('validation_rules->category', 'counter')
            ->orderBy('sequence');

        $counterParameters = $query
            ->get(['key', 'label'])
            ->mapWithKeys(fn (ParameterDefinition $parameter): array => [
                $parameter->key => "{$parameter->label} ({$parameter->key})",
            ])
            ->all();

        if ($counterParameters !== []) {
            return $counterParameters;
        }

        return $this->parameterOptionsForTopic($topicId);
    }

    /**
     * @return array<int|string, string>
     */
    private function numericParameterOptionsForTopic(mixed $topicId): array
    {
        if (! is_numeric($topicId)) {
            return [];
        }

        return ParameterDefinition::query()
            ->where('schema_version_topic_id', (int) $topicId)
            ->where('is_active', true)
            ->whereIn('type', [ParameterDataType::Integer->value, ParameterDataType::Decimal->value])
            ->orderBy('sequence')
            ->get(['key', 'label'])
            ->mapWithKeys(fn (ParameterDefinition $parameter): array => [
                $parameter->key => "{$parameter->label} ({$parameter->key})",
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function resolveWidgetFromArguments(array $arguments): ?IoTDashboardWidget
    {
        $dashboard = $this->selectedDashboard();
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
                ->body('Choose at least one parameter for the chart.')
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
     *     card_height_px: int,
     *     bar_interval?: string,
     *     gauge_style?: string,
     *     gauge_min?: int|float,
     *     gauge_max?: int|float,
     *     gauge_ranges?: array<int, array{from: int|float, to: int|float, color: string}>
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
        $barInterval = $this->sanitizeBarInterval(
            $data['bar_interval']
            ?? data_get($widget?->options, 'bar_interval')
            ?? BarInterval::Hourly->value,
        );

        $options = [
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

        if ($widget?->type === self::WIDGET_TYPE_BAR_CHART || array_key_exists('bar_interval', $data)) {
            $options['bar_interval'] = $barInterval->value;
        }

        if ($widget?->type === self::WIDGET_TYPE_GAUGE_CHART || array_key_exists('gauge_style', $data)) {
            $options['gauge_style'] = $this->sanitizeGaugeStyle(
                $data['gauge_style'] ?? data_get($widget?->options, 'gauge_style'),
            )->value;
            $options['gauge_min'] = $this->sanitizeGaugeMinimum(
                $data['gauge_min'] ?? data_get($widget?->options, 'gauge_min'),
            );
            $options['gauge_max'] = $this->sanitizeGaugeMaximum(
                $data['gauge_max'] ?? data_get($widget?->options, 'gauge_max'),
                $options['gauge_min'],
            );
            $options['gauge_ranges'] = $this->sanitizeGaugeRanges(
                $data['gauge_ranges'] ?? data_get($widget?->options, 'gauge_ranges'),
                $options['gauge_min'],
                $options['gauge_max'],
            );
        }

        return $options;
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

    private function resolveWidgetBarInterval(IoTDashboardWidget $widget): BarInterval
    {
        return $this->sanitizeBarInterval(data_get($widget->options, 'bar_interval'));
    }

    private function sanitizeBarInterval(mixed $value): BarInterval
    {
        if (is_string($value)) {
            $interval = BarInterval::tryFrom(strtolower(trim($value)));

            if ($interval instanceof BarInterval) {
                return $interval;
            }
        }

        return BarInterval::Hourly;
    }

    private function resolveWidgetGaugeStyle(IoTDashboardWidget $widget): GaugeStyle
    {
        return $this->sanitizeGaugeStyle(data_get($widget->options, 'gauge_style'));
    }

    private function resolveWidgetGaugeMinimum(IoTDashboardWidget $widget): float
    {
        return $this->sanitizeGaugeMinimum(data_get($widget->options, 'gauge_min'));
    }

    private function resolveWidgetGaugeMaximum(IoTDashboardWidget $widget): float
    {
        $minimum = $this->resolveWidgetGaugeMinimum($widget);

        return $this->sanitizeGaugeMaximum(data_get($widget->options, 'gauge_max'), $minimum);
    }

    /**
     * @return array<int, array{from: int|float, to: int|float, color: string}>
     */
    private function resolveWidgetGaugeRanges(IoTDashboardWidget $widget): array
    {
        $minimum = $this->resolveWidgetGaugeMinimum($widget);
        $maximum = $this->resolveWidgetGaugeMaximum($widget);

        return $this->sanitizeGaugeRanges(data_get($widget->options, 'gauge_ranges'), $minimum, $maximum);
    }

    private function sanitizeGaugeStyle(mixed $value): GaugeStyle
    {
        if (is_string($value)) {
            $style = GaugeStyle::tryFrom(strtolower(trim($value)));

            if ($style instanceof GaugeStyle) {
                return $style;
            }
        }

        return GaugeStyle::Classic;
    }

    private function sanitizeGaugeMinimum(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function sanitizeGaugeMaximum(mixed $value, float $minimum): float
    {
        if (! is_numeric($value)) {
            return max($minimum + 1, 100.0);
        }

        $maximum = (float) $value;

        return $maximum > $minimum ? $maximum : $minimum + 1;
    }

    /**
     * @return array<int, array{from: int|float, to: int|float, color: string}>
     */
    private function sanitizeGaugeRanges(mixed $ranges, float $minimum, float $maximum): array
    {
        if (! is_array($ranges)) {
            return $this->defaultGaugeRanges();
        }

        $sanitized = [];

        foreach ($ranges as $range) {
            if (! is_array($range)) {
                continue;
            }

            $from = isset($range['from']) && is_numeric($range['from']) ? (float) $range['from'] : null;
            $to = isset($range['to']) && is_numeric($range['to']) ? (float) $range['to'] : null;
            $color = is_string($range['color'] ?? null) && trim((string) $range['color']) !== ''
                ? (string) $range['color']
                : null;

            if ($from === null || $to === null || $color === null) {
                continue;
            }

            if ($to <= $from) {
                continue;
            }

            $sanitized[] = [
                'from' => min(max($from, $minimum), $maximum),
                'to' => min(max($to, $minimum), $maximum),
                'color' => $color,
            ];
        }

        if ($sanitized === []) {
            return $this->defaultGaugeRanges();
        }

        usort($sanitized, fn (array $left, array $right): int => ($left['from'] <=> $right['from']));

        return $sanitized;
    }

    /**
     * @return array<int, array{from: int|float, to: int|float, color: string}>
     */
    private function defaultGaugeRanges(): array
    {
        return [
            ['from' => 0, 'to' => 50, 'color' => '#10b981'],
            ['from' => 50, 'to' => 80, 'color' => '#f59e0b'],
            ['from' => 80, 'to' => 100, 'color' => '#ef4444'],
        ];
    }

    private function sanitizeGridColumns(mixed $value): int
    {
        return min(max($this->toInt($value, 1), 1), self::GRID_STACK_COLUMNS);
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
        return min(max($this->toInt($value, 2), 2), 12);
    }

    private function sanitizeGridCoordinate(mixed $value): int
    {
        return max($this->toInt($value), 0);
    }

    private function sanitizeCardHeight(mixed $value): int
    {
        return min(max($this->toInt($value, 360), 260), 900);
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
        $optionsValue = $widget->getAttribute('options');
        $options = is_array($optionsValue) ? $optionsValue : [];
        $layout = data_get($options, 'layout', []);
        $fallbackW = $this->sanitizeGridColumns(data_get($options, 'grid_columns', self::DEFAULT_WIDGET_GRID_COLUMNS));
        $fallbackH = $this->gridRowsFromCardHeight(data_get($options, 'card_height_px', 360));
        $layoutColumns = max(
            1,
            $this->toInt(data_get($options, 'layout_columns', self::LEGACY_GRID_STACK_COLUMNS), self::LEGACY_GRID_STACK_COLUMNS),
        );
        $scaleFactor = self::GRID_STACK_COLUMNS / $layoutColumns;

        $x = $this->toInt(data_get($layout, 'x', 0));
        $w = $this->toInt(data_get($layout, 'w', $fallbackW), $fallbackW);

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
        $this->dispatch('iot-dashboard-widgets-updated', widgets: $this->getWidgetBootstrapPayloadProperty());
    }

    private function refreshDashboardComputedProperties(): void
    {
        unset($this->{'selectedDashboard'});
        unset($this->{'widgetBootstrapPayload'});
    }

    private function selectedDashboard(): ?IoTDashboardModel
    {
        return $this->getSelectedDashboardProperty();
    }

    private function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
