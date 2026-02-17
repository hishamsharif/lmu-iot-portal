import { chartThemeColors } from '../../runtime/theme';

function resolveBarSeriesTimestamps(series) {
    const timestamps = [];
    const seen = new Set();

    series.forEach((entry) => {
        entry.points.forEach((point) => {
            if (typeof point?.timestamp !== 'string' || seen.has(point.timestamp)) {
                return;
            }

            seen.add(point.timestamp);
            timestamps.push(point.timestamp);
        });
    });

    return timestamps.sort((left, right) => new Date(left).getTime() - new Date(right).getTime());
}

function formatBarTimestampLabel(timestamp, barInterval) {
    const date = new Date(timestamp);

    if (Number.isNaN(date.getTime())) {
        return timestamp;
    }

    if (barInterval === 'daily') {
        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        });
    }

    return date.toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function barChartOption(widget, series) {
    const theme = chartThemeColors();
    const timestamps = resolveBarSeriesTimestamps(series);
    const categories = timestamps.map((timestamp) => formatBarTimestampLabel(timestamp, widget?.bar_interval));

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
                color: theme.legendText,
                fontSize: 11,
            },
        },
        tooltip: {
            trigger: 'axis',
            backgroundColor: theme.tooltipBg,
            borderColor: theme.tooltipBorder,
            textStyle: {
                color: theme.tooltipText,
            },
        },
        xAxis: {
            type: 'category',
            data: categories,
            axisLine: { lineStyle: { color: theme.axisLineColor } },
            axisLabel: {
                color: theme.axisLabelColor,
                fontSize: 11,
            },
        },
        yAxis: {
            type: 'value',
            axisLine: { show: false },
            splitLine: { lineStyle: { color: theme.splitLineColor } },
            axisLabel: {
                color: theme.axisLabelColor,
                fontSize: 11,
            },
        },
        series: series.map((entry) => {
            const valuesByTimestamp = new Map(entry.points.map((point) => [point.timestamp, point.value]));

            return {
                name: entry.label,
                type: 'bar',
                barMaxWidth: 28,
                emphasis: { focus: 'series' },
                data: timestamps.map((timestamp) => valuesByTimestamp.get(timestamp) ?? 0),
            };
        }),
    };
}
