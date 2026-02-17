export function isDarkMode() {
    return document.documentElement.classList.contains('dark')
        || document.body.classList.contains('dark');
}

export function chartThemeColors() {
    const dark = isDarkMode();

    return {
        legendText: dark ? '#cbd5e1' : '#64748b',
        tooltipBg: dark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.96)',
        tooltipBorder: dark ? 'rgba(148, 163, 184, 0.3)' : 'rgba(203, 213, 225, 0.6)',
        tooltipText: dark ? '#e2e8f0' : '#1e293b',
        axisLineColor: dark ? 'rgba(148, 163, 184, 0.45)' : 'rgba(100, 116, 139, 0.35)',
        axisLabelColor: dark ? '#94a3b8' : '#64748b',
        splitLineColor: dark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(203, 213, 225, 0.5)',
    };
}

export function normalizeNumericValue(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) {
        return Number(value);
    }

    return null;
}
