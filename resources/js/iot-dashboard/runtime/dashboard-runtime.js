import { normalizeNumericValue } from './theme';
import { GridLayoutManager } from './grid-layout-manager';
import { PollingManager } from './polling-manager';
import { RealtimeManager } from './realtime-manager';
import { lineChartOption } from '../widgets/line-chart/renderer';
import { barChartOption } from '../widgets/bar-chart/renderer';
import { gaugeChartOption } from '../widgets/gauge-chart/renderer';

const WIDGET_TYPES = Object.freeze({
    lineChart: 'line_chart',
    barChart: 'bar_chart',
    gaugeChart: 'gauge_chart',
});

function buildChartOption(widget, series) {
    if (widget?.type === WIDGET_TYPES.gaugeChart) {
        return gaugeChartOption(widget, series);
    }

    if (widget?.type === WIDGET_TYPES.barChart) {
        return barChartOption(widget, series);
    }

    return lineChartOption(series);
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

class DashboardRuntime {
    constructor(config) {
        this.config = config;
        this.widgets = new Map();
        this.charts = new Map();
        this.resizeObservers = new Map();
        this.pollingManager = new PollingManager();
        this.realtimeManager = new RealtimeManager(
            (payload) => this.appendRealtimePayload(payload),
            () => this.syncPolling(),
        );
        this.gridManager = new GridLayoutManager((nodes) => this.resizeChartsForNodes(nodes));
        this.hydrationFrame = null;
        this.gridIntegrityObserver = null;
        this.gridIntegrityTimer = null;
        this.lastHydratedWidgets = Array.isArray(config?.widgets) ? config.widgets : [];
    }

    boot() {
        this.ensureGridIntegrityObserver();
        this.scheduleHydration(this.lastHydratedWidgets);
    }

    destroy() {
        if (this.hydrationFrame !== null) {
            cancelAnimationFrame(this.hydrationFrame);
            this.hydrationFrame = null;
        }

        if (this.gridIntegrityTimer !== null) {
            clearTimeout(this.gridIntegrityTimer);
            this.gridIntegrityTimer = null;
        }

        if (this.gridIntegrityObserver) {
            this.gridIntegrityObserver.disconnect();
            this.gridIntegrityObserver = null;
        }

        this.pollingManager.destroy();
        this.realtimeManager.destroy();
        this.gridManager.destroy();

        this.resizeObservers.forEach((observer) => {
            observer.disconnect();
        });

        this.resizeObservers.clear();

        this.charts.forEach((chart) => {
            if (!chart.isDisposed()) {
                chart.dispose();
            }
        });

        this.charts.clear();
        this.widgets.clear();
    }

    updateWidgets(rawWidgets) {
        this.lastHydratedWidgets = Array.isArray(rawWidgets) ? rawWidgets : [];
        this.scheduleHydration(this.lastHydratedWidgets);
    }

    scheduleHydration(rawWidgets) {
        this.lastHydratedWidgets = Array.isArray(rawWidgets) ? rawWidgets : [];

        if (this.hydrationFrame !== null) {
            cancelAnimationFrame(this.hydrationFrame);
        }

        this.hydrationFrame = requestAnimationFrame(() => {
            this.hydrationFrame = requestAnimationFrame(() => {
                this.hydrationFrame = null;
                this.hydrate(this.lastHydratedWidgets);
            });
        });
    }

    hydrate(rawWidgets) {
        this.pollingManager.stopAll();

        this.widgets.clear();

        rawWidgets.forEach((rawWidget) => {
            if (!rawWidget || typeof rawWidget.id !== 'number') {
                return;
            }

            const widget = {
                ...rawWidget,
                seriesData: normalizeSeriesConfiguration(rawWidget),
            };

            this.widgets.set(widget.id, widget);
            this.renderWidget(widget);
            this.requestWidgetSnapshot(widget);
        });

        this.cleanupOrphanCharts();

        this.realtimeManager.update(this.config.organization_id, Array.from(this.widgets.values()));
        this.syncPolling();

        this.mountGrid();
    }

    syncPolling() {
        const widgets = Array.from(this.widgets.values());

        this.pollingManager.sync(
            widgets,
            (widget) => this.realtimeManager.shouldPollWidget(widget),
            (widget) => this.requestWidgetSnapshot(widget),
        );
    }

    mountGrid() {
        const container = document.getElementById('iot-dashboard-grid');
        this.gridManager.mount(container, Array.from(this.widgets.values()));

        window.requestAnimationFrame(() => {
            this.resizeAllCharts();
        });
    }

    hasGridStackRuntimeState(container) {
        if (!container) {
            return false;
        }

        const hasColumnClass = container.classList.contains('gs-24')
            || container.classList.contains('gs-1');

        if (!hasColumnClass) {
            return false;
        }

        if (container.getAttribute('gs-current-row') === null) {
            return false;
        }

        return document.querySelectorAll('style[gs-style-id]').length > 0;
    }

    ensureGridIntegrity() {
        if (this.lastHydratedWidgets.length === 0) {
            return;
        }

        const container = document.getElementById('iot-dashboard-grid');

        if (!container) {
            return;
        }

        const gridMatchesContainer = this.gridManager.isMountedOn(container);

        if (gridMatchesContainer && this.hasGridStackRuntimeState(container)) {
            return;
        }

        this.scheduleHydration(this.lastHydratedWidgets);
    }

    scheduleGridIntegrityCheck() {
        if (this.gridIntegrityTimer !== null) {
            clearTimeout(this.gridIntegrityTimer);
        }

        this.gridIntegrityTimer = window.setTimeout(() => {
            this.gridIntegrityTimer = null;
            this.ensureGridIntegrity();
        }, 120);
    }

    ensureGridIntegrityObserver() {
        if (this.gridIntegrityObserver || typeof window.MutationObserver === 'undefined') {
            return;
        }

        this.gridIntegrityObserver = new window.MutationObserver(() => {
            this.scheduleGridIntegrityCheck();
        });

        this.gridIntegrityObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style'],
        });
    }

    cleanupOrphanCharts() {
        const widgetIds = new Set(Array.from(this.widgets.keys()));

        Array.from(this.charts.keys()).forEach((widgetId) => {
            if (widgetIds.has(widgetId)) {
                return;
            }

            const chart = this.charts.get(widgetId);

            if (chart && !chart.isDisposed()) {
                chart.dispose();
            }

            this.charts.delete(widgetId);

            const observer = this.resizeObservers.get(widgetId);

            if (observer) {
                observer.disconnect();
                this.resizeObservers.delete(widgetId);
            }
        });
    }

    resizeAllCharts() {
        this.charts.forEach((chart) => {
            if (!chart.isDisposed()) {
                chart.resize();
            }
        });
    }

    resizeChartsForNodes(nodes) {
        if (!Array.isArray(nodes)) {
            return;
        }

        nodes.forEach((node) => {
            const widgetId = this.gridManager.parseWidgetIdFromNode(node);

            if (widgetId === null) {
                return;
            }

            const chart = this.charts.get(widgetId);

            if (chart && !chart.isDisposed()) {
                chart.resize();
            }
        });
    }

    ensureChart(widgetId) {
        if (!window.echarts) {
            return null;
        }

        const target = document.getElementById(`iot-widget-chart-${widgetId}`);

        if (!target) {
            return null;
        }

        const existing = this.charts.get(widgetId);

        if (existing && !existing.isDisposed()) {
            const existingTarget = typeof existing.getDom === 'function'
                ? existing.getDom()
                : null;
            const targetWasReplaced = existingTarget !== target;
            const targetWasEmptied = target.childElementCount === 0;

            if (!targetWasReplaced && !targetWasEmptied) {
                return existing;
            }

            existing.dispose();
            this.charts.delete(widgetId);
        }

        const existingObserver = this.resizeObservers.get(widgetId);

        if (existingObserver) {
            existingObserver.disconnect();
            this.resizeObservers.delete(widgetId);
        }

        const chart = window.echarts.init(target);
        this.charts.set(widgetId, chart);

        if (typeof window.ResizeObserver !== 'undefined') {
            const observer = new window.ResizeObserver(() => {
                if (!chart.isDisposed()) {
                    chart.resize();
                }
            });

            observer.observe(target);
            this.resizeObservers.set(widgetId, observer);
        }

        return chart;
    }

    renderWidget(widget) {
        const chart = this.ensureChart(widget.id);

        if (!chart) {
            return;
        }

        chart.setOption(buildChartOption(widget, widget.seriesData), true);
    }

    requestWidgetSnapshot(widget) {
        if (!window.axios || typeof widget.snapshot_url !== 'string' || widget.snapshot_url.trim() === '') {
            return;
        }

        window.axios
            .get(widget.snapshot_url)
            .then((response) => {
                this.applySnapshotResponse(widget, response.data);
            })
            .catch((error) => {
                console.error('IoT dashboard polling failed', error);
            });
    }

    applySnapshotResponse(widget, payload) {
        const snapshot = Array.isArray(payload?.widgets)
            ? payload.widgets.find((item) => Number(item?.id) === Number(widget.id))
            : payload;

        if (!snapshot || !Array.isArray(snapshot.series)) {
            return;
        }

        this.applySnapshotSeries(widget, snapshot.series);
    }

    applySnapshotSeries(widget, snapshotSeries) {
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

        this.renderWidget(widget);
    }

    appendRealtimePayload(payload) {
        const topicId = Number(payload?.schema_version_topic_id ?? 0);
        const organizationId = Number(payload?.organization_id ?? 0);

        if (!Number.isInteger(topicId) || topicId <= 0) {
            return;
        }

        if (Number.isInteger(this.config.organization_id) && this.config.organization_id > 0 && organizationId > 0) {
            if (organizationId !== Number(this.config.organization_id)) {
                return;
            }
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

        this.widgets.forEach((widget) => {
            if (!this.isMatchingRealtimeStream(widget, topicId, deviceUuid)) {
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

                const minPoints = widget?.type === WIDGET_TYPES.gaugeChart ? 1 : 20;
                const maxPoints = Math.max(minPoints, Number(widget.max_points || 240));

                if (nextPoints.length > maxPoints) {
                    nextPoints.splice(0, nextPoints.length - maxPoints);
                }

                return {
                    ...series,
                    points: nextPoints,
                };
            });

            this.renderWidget(widget);
        });
    }

    isMatchingRealtimeStream(widget, topicId, deviceUuid) {
        const widgetTopicId = Number(widget.topic?.id ?? 0);
        const widgetDeviceUuid = typeof widget.device?.uuid === 'string'
            ? widget.device.uuid
            : null;

        if (!widget.use_websocket || widgetTopicId !== topicId || widget?.type === WIDGET_TYPES.barChart) {
            return false;
        }

        if (typeof widgetDeviceUuid !== 'string' || widgetDeviceUuid.trim() === '') {
            return false;
        }

        return widgetDeviceUuid === deviceUuid;
    }
}

let runtime = null;

export function bootDashboardRuntime(config) {
    if (runtime) {
        runtime.destroy();
    }

    runtime = new DashboardRuntime(config);
    runtime.boot();

    return runtime;
}

export function updateDashboardRuntimeWidgets(widgets) {
    if (!runtime) {
        return;
    }

    runtime.updateWidgets(widgets);
}

export function destroyDashboardRuntime() {
    if (!runtime) {
        return;
    }

    runtime.destroy();
    runtime = null;
}
