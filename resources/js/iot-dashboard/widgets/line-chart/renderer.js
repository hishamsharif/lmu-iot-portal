import { chartThemeColors } from '../../runtime/theme';

export function lineChartOption(series) {
    const theme = chartThemeColors();

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
            type: 'time',
            axisLine: { lineStyle: { color: theme.axisLineColor } },
            splitLine: { show: false },
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
