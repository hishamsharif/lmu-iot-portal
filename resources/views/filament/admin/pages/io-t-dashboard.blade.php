<x-filament-panels::page>
    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack-extra.min.css">
    @endpush

    <style>
        .iot-dashboard-shell {
            display: grid;
            gap: 1rem;
        }

        .iot-dashboard-grid {
            min-height: 340px;
        }

        .iot-dashboard-grid.grid-stack > .grid-stack-item > .grid-stack-item-content {
            margin: 0;
        }

        .iot-widget-card {
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 0.75rem;
            overflow: hidden;
            background: linear-gradient(160deg, rgba(15, 23, 42, 0.98), rgba(2, 6, 23, 0.98));
            box-shadow: 0 12px 30px rgba(2, 6, 23, 0.25);
            display: grid;
            grid-template-rows: auto 1fr;
            min-height: 100%;
        }

        .iot-widget-card header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        }

        .iot-widget-title {
            font-size: 0.95rem;
            line-height: 1.3rem;
            font-weight: 700;
            color: #f8fafc;
            margin: 0;
        }

        .iot-widget-meta {
            margin-top: 0.25rem;
            font-size: 0.74rem;
            line-height: 1.05rem;
            color: #cbd5e1;
        }

        .iot-widget-flags {
            display: inline-flex;
            gap: 0.35rem;
            align-items: center;
        }

        .iot-widget-chart {
            min-height: 220px;
            height: 100%;
            width: 100%;
        }

        .iot-empty-state {
            padding: 1.35rem 1rem;
            border: 1px dashed rgba(148, 163, 184, 0.35);
            border-radius: 0.75rem;
            color: rgb(100, 116, 139);
            font-size: 0.875rem;
        }

        .grid-stack-placeholder > .placeholder-content {
            border-radius: 0.75rem;
            border: 1px dashed rgba(34, 211, 238, 0.45);
            background: rgba(2, 132, 199, 0.08);
        }

        .gs-24 > .grid-stack-item {
            width: calc(100% / 24);
        }

        @for ($column = 1; $column <= 24; $column++)
            .gs-24 > .grid-stack-item[gs-x="{{ $column }}"] {
                left: calc((100% / 24) * {{ $column }});
            }

            .gs-24 > .grid-stack-item[gs-w="{{ $column }}"] {
                width: calc((100% / 24) * {{ $column }});
            }
        @endfor
    </style>

    <div class="iot-dashboard-shell">
        @if (! $this->selectedDashboard)
            <x-filament::section
                heading="No Dashboard Selected"
                description="Open a dashboard from the Dashboards list to configure widgets and visualize telemetry."
            >
                <div class="iot-empty-state">
                    No dashboard was selected. Use <strong>Dashboards</strong> and open one from the table action.
                </div>

                <div class="mt-4">
                    <x-filament::button tag="a" href="{{ \App\Filament\Admin\Resources\IoTDashboards\IoTDashboardResource::getUrl() }}">
                        Open Dashboards
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            <x-filament::section
                :heading="$this->selectedDashboard->name"
                :description="$this->selectedDashboard->description ?: 'Widgets are scoped by topic + device. Drag and resize cards to snap on the grid without overlap.'"
            >
                @if ($this->selectedDashboard->widgets->isEmpty())
                    <div class="iot-empty-state">
                        No widgets yet. Click <strong>Add Line Widget</strong>, choose a topic, then choose the exact device and parameters.
                    </div>
                @else
                    @php(
                        $widgetLayouts = collect($this->widgetBootstrapPayload)
                            ->mapWithKeys(fn (array $widget): array => [(int) $widget['id'] => (array) ($widget['layout'] ?? [])])
                            ->all()
                    )

                    <div class="iot-dashboard-grid grid-stack" id="iot-dashboard-grid">
                        @foreach ($this->selectedDashboard->widgets as $widget)
                            @php($layout = $widgetLayouts[(int) $widget->id] ?? [])
                            @php($gridSpan = max(1, min(24, (int) data_get($layout, 'w', (int) data_get($widget->options, 'grid_columns', 1)))))
                            @php($gridHeight = max(2, min(12, (int) data_get($layout, 'h', (int) ceil(max(260, min(900, (int) data_get($widget->options, 'card_height_px', 360))) / 96)))))
                            @php($gridX = max(0, (int) data_get($layout, 'x', 0)))
                            @php($gridY = max(0, (int) data_get($layout, 'y', 0)))

                            <div
                                class="grid-stack-item"
                                gs-id="{{ $widget->id }}"
                                gs-x="{{ $gridX }}"
                                gs-y="{{ $gridY }}"
                                gs-w="{{ $gridSpan }}"
                                gs-h="{{ $gridHeight }}"
                            >
                                <article class="iot-widget-card grid-stack-item-content">
                                    <header>
                                        <div>
                                            <h3 class="iot-widget-title">{{ $widget->title }}</h3>
                                            <p class="iot-widget-meta">
                                                {{ $widget->topic?->label ?? 'Unknown topic' }}
                                                @if ($widget->topic?->suffix)
                                                    ({{ $widget->topic->suffix }})
                                                @endif
                                                ·
                                                {{ $widget->device?->name ?? 'Unknown device' }}
                                            </p>
                                        </div>

                                        <div class="iot-widget-flags">
                                            <x-filament::badge :color="$widget->use_websocket ? 'success' : 'gray'" size="sm">
                                                WS
                                            </x-filament::badge>
                                            <x-filament::badge :color="$widget->use_polling ? 'info' : 'gray'" size="sm">
                                                Poll
                                            </x-filament::badge>
                                            {{ ($this->editWidgetAction)(['widget' => $widget->id]) }}
                                            <x-filament::icon-button
                                                icon="heroicon-o-trash"
                                                color="danger"
                                                size="sm"
                                                wire:click="deleteWidget({{ $widget->id }})"
                                            />
                                        </div>
                                    </header>

                                    <div class="iot-widget-chart" id="iot-widget-chart-{{ $widget->id }}"></div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack-all.js"></script>
        <script>
            window.iotDashboardWidgets = @js($this->widgetBootstrapPayload);
            window.iotDashboardRealtimeConfig = {
                key: @js(config('broadcasting.connections.reverb.key')),
                host: @js(config('broadcasting.connections.reverb.options.host')),
                port: @js(config('broadcasting.connections.reverb.options.port')),
                scheme: @js(config('broadcasting.connections.reverb.options.scheme')),
            };
        </script>
    @endpush
</x-filament-panels::page>
