const GRID_STACK_COLUMNS = 24;
const GRID_STACK_CELL_HEIGHT = 96;
const GRID_STACK_MARGIN = '20px 12px';
const MOBILE_BREAKPOINT = '(max-width: 768px)';

const dashboardState = {
    charts: new Map(),
    pollingTimers: new Map(),
    resizeObservers: new Map(),
    widgets: new Map(),
    pendingLayoutUpdates: new Map(),
    layoutSaveTimer: null,
    grid: null,
    pusher: null,
    channel: null,
    resizeBound: false,
    isHydrating: false,
    hydrateFrameId: null,
    pendingWidgets: [],
};

function normalizeNumericValue(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) {
        return Number(value);
    }

    return null;
}

function buildChartOption(widget, series) {
    return {
        backgroundColor: 'transparent',
        animation: true,
        color: series.map((entry) => entry.color),
        grid: {
            left: 42,
            right: 18,
            top: 34,
            bottom: 34,
            containLabel: true,
        },
        legend: {
            top: 4,
            textStyle: {
                color: '#cbd5e1',
                fontSize: 11,
            },
        },
        tooltip: {
            trigger: 'axis',
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            borderColor: 'rgba(148, 163, 184, 0.3)',
            textStyle: {
                color: '#e2e8f0',
            },
        },
        xAxis: {
            type: 'time',
            axisLine: { lineStyle: { color: 'rgba(148, 163, 184, 0.45)' } },
            splitLine: { show: false },
            axisLabel: {
                color: '#94a3b8',
                fontSize: 11,
            },
        },
        yAxis: {
            type: 'value',
            axisLine: { show: false },
            splitLine: { lineStyle: { color: 'rgba(148, 163, 184, 0.15)' } },
            axisLabel: {
                color: '#94a3b8',
                fontSize: 11,
            },
        },
        series: series.map((entry) => ({
            name: entry.label,
            type: 'line',
            smooth: true,
            showSymbol: false,
            sampling: 'lttb',
            emphasis: { focus: 'series' },
            lineStyle: {
                width: 2,
                color: entry.color,
            },
            data: entry.points.map((point) => [point.timestamp, point.value]),
        })),
    };
}

function destroyGrid() {
    if (dashboardState.grid) {
        try {
            dashboardState.grid.destroy(false);
        } catch (error) {
            const isKnownDestroyIssue = error instanceof Error && error.name === 'NotFoundError';

            if (!isKnownDestroyIssue) {
                console.warn('GridStack destroy failed', error);
            }
        }

        const container = document.getElementById('iot-dashboard-grid');

        if (container) {
            try {
                delete container.gridstack;
            } catch (_error) {
                container.gridstack = undefined;
            }

            container
                .querySelectorAll('.ui-resizable-handle, .grid-stack-placeholder')
                .forEach((element) => element.remove());

            container.querySelectorAll('.grid-stack-item').forEach((item) => {
                delete item.gridstackNode;
                delete item.ddElement;
                item.classList.remove('ui-draggable-disabled', 'ui-resizable-disabled', 'ui-resizable-autohide');
            });
        }

        dashboardState.grid = null;
    }
}

function clearChartsAndPolling() {
    for (const timer of dashboardState.pollingTimers.values()) {
        clearInterval(timer);
    }

    dashboardState.pollingTimers.clear();

    for (const observer of dashboardState.resizeObservers.values()) {
        observer.disconnect();
    }

    dashboardState.resizeObservers.clear();

    if (dashboardState.layoutSaveTimer) {
        clearTimeout(dashboardState.layoutSaveTimer);
        dashboardState.layoutSaveTimer = null;
    }

    if (dashboardState.hydrateFrameId !== null) {
        cancelAnimationFrame(dashboardState.hydrateFrameId);
        dashboardState.hydrateFrameId = null;
    }

    dashboardState.pendingLayoutUpdates.clear();

    for (const chart of dashboardState.charts.values()) {
        if (!chart.isDisposed()) {
            chart.dispose();
        }
    }

    dashboardState.charts.clear();
    dashboardState.widgets.clear();
    destroyGrid();
}

function ensureResizeBinding() {
    if (dashboardState.resizeBound) {
        return;
    }

    dashboardState.resizeBound = true;

    window.addEventListener('resize', () => {
        const isMobile = window.matchMedia(MOBILE_BREAKPOINT).matches;

        if (dashboardState.grid) {
            dashboardState.grid.column(isMobile ? 1 : GRID_STACK_COLUMNS);
            dashboardState.grid.enableMove(!isMobile);
            dashboardState.grid.enableResize(!isMobile);
        }

        for (const chart of dashboardState.charts.values()) {
            if (!chart.isDisposed()) {
                chart.resize();
            }
        }
    });
}

function ensureChart(widgetId) {
    const existing = dashboardState.charts.get(widgetId);

    if (existing && !existing.isDisposed()) {
        return existing;
    }

    const target = document.getElementById(`iot-widget-chart-${widgetId}`);

    if (!target || !window.echarts) {
        return null;
    }

    const chart = window.echarts.init(target);
    dashboardState.charts.set(widgetId, chart);

    if (!dashboardState.resizeObservers.has(widgetId) && typeof window.ResizeObserver !== 'undefined') {
        const observer = new window.ResizeObserver(() => {
            if (!chart.isDisposed()) {
                chart.resize();
            }
        });

        observer.observe(target);
        dashboardState.resizeObservers.set(widgetId, observer);
    }

    return chart;
}

function normalizeSeriesConfiguration(widget) {
    if (!Array.isArray(widget.series)) {
        return [];
    }

    return widget.series
        .filter((entry) => typeof entry?.key === 'string' && entry.key.trim() !== '')
        .map((entry) => ({
            key: entry.key,
            label: typeof entry.label === 'string' && entry.label.trim() !== '' ? entry.label : entry.key,
            color: typeof entry.color === 'string' && entry.color.trim() !== '' ? entry.color : '#38bdf8',
            points: [],
        }));
}

function renderWidget(widget) {
    const chart = ensureChart(widget.id);

    if (!chart) {
        return;
    }

    chart.setOption(buildChartOption(widget, widget.seriesData), true);
}

function applySnapshot(widget, payload) {
    const snapshotSeries = Array.isArray(payload?.series) ? payload.series : [];
    const seriesByKey = new Map();

    snapshotSeries.forEach((entry) => {
        if (typeof entry?.key !== 'string') {
            return;
        }

        const points = Array.isArray(entry.points)
            ? entry.points
                .map((point) => {
                    const value = normalizeNumericValue(point?.value);

                    if (value === null || typeof point?.timestamp !== 'string') {
                        return null;
                    }

                    return {
                        timestamp: point.timestamp,
                        value,
                    };
                })
                .filter(Boolean)
            : [];

        seriesByKey.set(entry.key, points);
    });

    widget.seriesData = widget.seriesData.map((series) => ({
        ...series,
        points: seriesByKey.has(series.key) ? seriesByKey.get(series.key) : [],
    }));

    renderWidget(widget);
}

function requestWidgetSnapshot(widget) {
    if (!window.axios || typeof widget.data_url !== 'string' || widget.data_url.trim() === '') {
        return;
    }

    window.axios
        .get(widget.data_url, {
            params: {
                lookback_minutes: widget.lookback_minutes,
                max_points: widget.max_points,
            },
        })
        .then((response) => {
            applySnapshot(widget, response.data);
        })
        .catch((error) => {
            console.error('IoT dashboard polling failed', error);
        });
}

function enablePolling(widget) {
    if (!widget.use_polling) {
        return;
    }

    const intervalSeconds = Math.max(2, Number(widget.polling_interval_seconds || 10));
    const intervalId = window.setInterval(() => {
        requestWidgetSnapshot(widget);
    }, intervalSeconds * 1000);

    dashboardState.pollingTimers.set(widget.id, intervalId);
}

function isMatchingWidgetStream(widget, topicId, deviceUuid) {
    const widgetTopicId = Number(widget.topic?.id ?? 0);
    const widgetDeviceUuid = typeof widget.device?.uuid === 'string'
        ? widget.device.uuid
        : null;

    if (!widget.use_websocket || widgetTopicId !== topicId) {
        return false;
    }

    if (typeof widgetDeviceUuid !== 'string' || widgetDeviceUuid.trim() === '') {
        return false;
    }

    return widgetDeviceUuid === deviceUuid;
}

function appendRealtimePayload(payload) {
    const topicId = Number(payload?.schema_version_topic_id ?? 0);

    if (!Number.isInteger(topicId) || topicId <= 0) {
        return;
    }

    const deviceUuid = typeof payload?.device_uuid === 'string'
        ? payload.device_uuid
        : null;

    if (typeof deviceUuid !== 'string' || deviceUuid.trim() === '') {
        return;
    }

    const transformedValues = payload?.transformed_values;

    if (typeof transformedValues !== 'object' || transformedValues === null) {
        return;
    }

    const recordedAt = typeof payload.recorded_at === 'string'
        ? payload.recorded_at
        : new Date().toISOString();

    dashboardState.widgets.forEach((widget) => {
        if (!isMatchingWidgetStream(widget, topicId, deviceUuid)) {
            return;
        }

        widget.seriesData = widget.seriesData.map((series) => {
            const value = normalizeNumericValue(transformedValues[series.key]);

            if (value === null) {
                return series;
            }

            const nextPoints = [...series.points, {
                timestamp: recordedAt,
                value,
            }];

            const maxPoints = Math.max(20, Number(widget.max_points || 240));

            if (nextPoints.length > maxPoints) {
                nextPoints.splice(0, nextPoints.length - maxPoints);
            }

            return {
                ...series,
                points: nextPoints,
            };
        });

        renderWidget(widget);
    });
}

function ensureRealtimeChannel() {
    const shouldSubscribe = Array.from(dashboardState.widgets.values()).some((widget) => widget.use_websocket);

    if (!shouldSubscribe) {
        return;
    }

    if (!window.Pusher || dashboardState.channel) {
        return;
    }

    const realtimeConfig = window.iotDashboardRealtimeConfig ?? {};

    if (typeof realtimeConfig.key !== 'string' || realtimeConfig.key.trim() === '') {
        return;
    }

    dashboardState.pusher = new window.Pusher(realtimeConfig.key, {
        cluster: 'mt1',
        wsHost: realtimeConfig.host,
        wsPort: realtimeConfig.port,
        wssPort: realtimeConfig.port,
        forceTLS: (realtimeConfig.scheme ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });

    dashboardState.channel = dashboardState.pusher.subscribe('telemetry');
    dashboardState.channel.bind('telemetry.received', appendRealtimePayload);
}

function parseWidgetIdFromNode(node) {
    const idValue = node?.id ?? node?.el?.getAttribute('gs-id') ?? null;
    const numericId = Number(idValue);

    if (!Number.isInteger(numericId) || numericId <= 0) {
        return null;
    }

    return numericId;
}

function resizeChartsForNodes(nodes) {
    if (!Array.isArray(nodes)) {
        return;
    }

    nodes.forEach((node) => {
        const widgetId = parseWidgetIdFromNode(node);

        if (widgetId === null) {
            return;
        }

        const chart = dashboardState.charts.get(widgetId);

        if (chart && !chart.isDisposed()) {
            chart.resize();
        }
    });
}

function queueLayoutPersistence(nodes) {
    if (!Array.isArray(nodes) || nodes.length === 0) {
        return;
    }

    nodes.forEach((node) => {
        const widgetId = parseWidgetIdFromNode(node);

        if (widgetId === null) {
            return;
        }

        const widget = dashboardState.widgets.get(widgetId);

        if (!widget || typeof widget.layout_url !== 'string' || widget.layout_url.trim() === '') {
            return;
        }

        const x = Math.max(0, Number(node.x ?? 0));
        const y = Math.max(0, Number(node.y ?? 0));
        const w = Math.max(1, Math.min(GRID_STACK_COLUMNS, Number(node.w ?? 1)));
        const h = Math.max(2, Math.min(12, Number(node.h ?? 4)));

        if (!Number.isFinite(x) || !Number.isFinite(y) || !Number.isFinite(w) || !Number.isFinite(h)) {
            return;
        }

        dashboardState.pendingLayoutUpdates.set(widgetId, {
            widget,
            layout: {
                x,
                y,
                w,
                h,
            },
        });
    });

    if (dashboardState.layoutSaveTimer) {
        clearTimeout(dashboardState.layoutSaveTimer);
    }

    dashboardState.layoutSaveTimer = window.setTimeout(() => {
        const updates = Array.from(dashboardState.pendingLayoutUpdates.values());
        dashboardState.pendingLayoutUpdates.clear();

        updates.forEach(({ widget, layout }) => {
            window.axios
                .post(widget.layout_url, layout)
                .catch((error) => {
                    console.error('Failed to persist dashboard widget layout', error);
                });

            widget.layout = layout;
        });
    }, 250);
}

function initializeGridStack() {
    const container = document.getElementById('iot-dashboard-grid');

    if (!container || !window.GridStack) {
        return;
    }

    destroyGrid();

    const isMobile = window.matchMedia(MOBILE_BREAKPOINT).matches;

    dashboardState.grid = window.GridStack.init({
        column: isMobile ? 1 : GRID_STACK_COLUMNS,
        margin: GRID_STACK_MARGIN,
        cellHeight: GRID_STACK_CELL_HEIGHT,
        float: false,
        animate: true,
        disableDrag: isMobile,
        disableResize: isMobile,
    }, container);

    dashboardState.grid.on('change', (_event, changedItems) => {
        resizeChartsForNodes(changedItems);

        if (dashboardState.isHydrating) {
            return;
        }

        queueLayoutPersistence(changedItems);
    });

    dashboardState.grid.on('resizestop', (_event, element) => {
        resizeChartsForNodes([{ el: element }]);
    });

    window.setTimeout(() => {
        for (const chart of dashboardState.charts.values()) {
            if (!chart.isDisposed()) {
                chart.resize();
            }
        }
    }, 0);
}

function hydrateWidgets(rawWidgets) {
    clearChartsAndPolling();
    dashboardState.isHydrating = true;

    if (!Array.isArray(rawWidgets) || rawWidgets.length === 0) {
        dashboardState.isHydrating = false;
        return;
    }

    rawWidgets.forEach((rawWidget) => {
        if (!rawWidget || typeof rawWidget.id !== 'number') {
            return;
        }

        const widget = {
            ...rawWidget,
            seriesData: normalizeSeriesConfiguration(rawWidget),
        };

        dashboardState.widgets.set(widget.id, widget);
        renderWidget(widget);
        requestWidgetSnapshot(widget);
        enablePolling(widget);
    });

    initializeGridStack();
    dashboardState.isHydrating = false;
    ensureRealtimeChannel();
}

function scheduleHydration(rawWidgets) {
    dashboardState.pendingWidgets = Array.isArray(rawWidgets) ? rawWidgets : [];

    if (dashboardState.hydrateFrameId !== null) {
        cancelAnimationFrame(dashboardState.hydrateFrameId);
    }

    dashboardState.hydrateFrameId = requestAnimationFrame(() => {
        dashboardState.hydrateFrameId = requestAnimationFrame(() => {
            dashboardState.hydrateFrameId = null;
            hydrateWidgets(dashboardState.pendingWidgets);
        });
    });
}

function bootDashboardGrid() {
    ensureResizeBinding();
    scheduleHydration(window.iotDashboardWidgets ?? []);
}

if (!window.__iotDashboardGridBooted) {
    window.__iotDashboardGridBooted = true;

    document.addEventListener('DOMContentLoaded', bootDashboardGrid);
    document.addEventListener('livewire:navigated', bootDashboardGrid);

    window.addEventListener('iot-dashboard-widgets-updated', (event) => {
        const widgets = Array.isArray(event?.detail?.widgets)
            ? event.detail.widgets
            : [];

        window.iotDashboardWidgets = widgets;
        scheduleHydration(widgets);
    });
}
