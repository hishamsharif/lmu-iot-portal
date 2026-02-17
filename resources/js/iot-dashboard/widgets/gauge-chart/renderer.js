import { chartThemeColors, normalizeNumericValue } from '../../runtime/theme';

function resolveGaugeAxisLineColors(rawRanges, minimum, maximum) {
    if (!Array.isArray(rawRanges) || rawRanges.length === 0 || maximum <= minimum) {
        return [[1, '#22d3ee']];
    }

    const ranges = rawRanges
        .filter((range) => range && Number.isFinite(Number(range.to)) && typeof range.color === 'string')
        .map((range) => ({
            to: Number(range.to),
            color: range.color,
        }))
        .sort((left, right) => left.to - right.to);

    if (ranges.length === 0) {
        return [[1, '#22d3ee']];
    }

    const resolved = [];

    ranges.forEach((range) => {
        const threshold = Math.min(Math.max((range.to - minimum) / (maximum - minimum), 0), 1);
        resolved.push([threshold, range.color]);
    });

    if (resolved[resolved.length - 1][0] < 1) {
        resolved.push([1, resolved[resolved.length - 1][1]]);
    }

    return resolved;
}

export function gaugeChartOption(widget, series) {
    const theme = chartThemeColors();
    const primarySeries = Array.isArray(series) && series.length > 0 ? series[0] : null;
    const latestPoint = primarySeries?.points?.length > 0
        ? primarySeries.points[primarySeries.points.length - 1]
        : null;
    const currentValue = normalizeNumericValue(latestPoint?.value) ?? 0;
    const minimum = Number.isFinite(Number(widget?.gauge_min)) ? Number(widget.gauge_min) : 0;
    const maximum = Number.isFinite(Number(widget?.gauge_max)) ? Number(widget.gauge_max) : 100;
    const normalizedMaximum = maximum > minimum ? maximum : minimum + 1;
    const style = typeof widget?.gauge_style === 'string' ? widget.gauge_style : 'classic';

    return {
        backgroundColor: 'transparent',
        animation: true,
        tooltip: {
            formatter: '{a}<br/>{b}: {c}',
            backgroundColor: theme.tooltipBg,
            borderColor: theme.tooltipBorder,
            textStyle: {
                color: theme.tooltipText,
            },
        },
        series: [
            {
                type: 'gauge',
                min: minimum,
                max: normalizedMaximum,
                progress: {
                    show: style === 'progress',
                    width: style === 'minimal' ? 8 : 14,
                    roundCap: style !== 'classic',
                },
                axisLine: {
                    roundCap: style !== 'classic',
                    lineStyle: {
                        width: style === 'minimal' ? 8 : 14,
                        color: resolveGaugeAxisLineColors(widget?.gauge_ranges, minimum, normalizedMaximum),
                    },
                },
                pointer: {
                    show: style !== 'minimal',
                    length: style === 'progress' ? '72%' : '65%',
                    width: style === 'minimal' ? 2 : 5,
                },
                anchor: {
                    show: style !== 'minimal',
                    size: 10,
                },
                axisTick: { show: style === 'classic' },
                splitLine: { show: style === 'classic' },
                axisLabel: { show: style === 'classic' },
                title: {
                    color: theme.axisLabelColor,
                    fontSize: 12,
                    offsetCenter: [0, '70%'],
                },
                detail: {
                    valueAnimation: true,
                    fontSize: 24,
                    color: theme.tooltipText,
                    offsetCenter: [0, style === 'minimal' ? '10%' : '30%'],
                    formatter: (value) => `${Number(value).toFixed(2)}`,
                },
                data: [
                    {
                        value: currentValue,
                        name: primarySeries?.label ?? 'Value',
                    },
                ],
            },
        ],
    };
}
