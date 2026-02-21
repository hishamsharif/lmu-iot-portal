import {
    CONDITION_LEFT_OPTIONS,
    NODE_PALETTE,
    QUERY_ALIAS_REGEX,
    WINDOW_UNITS,
} from '../constants.js';

export function isPlainObject(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function toPositiveInteger(value) {
    if (typeof value === 'number' && Number.isInteger(value) && value > 0) {
        return value;
    }

    if (typeof value === 'string' && /^\d+$/.test(value)) {
        const parsed = Number(value);

        return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
    }

    return null;
}

export function detectDarkMode() {
    return document.documentElement.classList.contains('dark') || document.body?.classList.contains('dark');
}

export function paletteLabel(type) {
    const match = NODE_PALETTE.find((node) => node.type === type);

    return match?.label ?? 'Node';
}

export function normalizePosition(position, index) {
    if (isPlainObject(position) && Number.isFinite(position.x) && Number.isFinite(position.y)) {
        return {
            x: Number(position.x),
            y: Number(position.y),
        };
    }

    return {
        x: 160 + (index % 3) * 320,
        y: 130 + Math.floor(index / 3) * 190,
    };
}

export function nodeColorForMiniMap(nodeType, isDark) {
    const palette = {
        'telemetry-trigger': isDark ? '#fbbf24' : '#f59e0b',
        'schedule-trigger': isDark ? '#fbbf24' : '#f59e0b',
        condition: isDark ? '#60a5fa' : '#2563eb',
        query: isDark ? '#22d3ee' : '#0891b2',
        delay: isDark ? '#a78bfa' : '#7c3aed',
        command: isDark ? '#34d399' : '#059669',
        alert: isDark ? '#f87171' : '#dc2626',
    };

    return palette[nodeType] ?? (isDark ? '#94a3b8' : '#64748b');
}

export function safeJsonStringify(value, spacing = 2) {
    try {
        return JSON.stringify(value, null, spacing);
    } catch {
        return '{}';
    }
}

export function compactJson(value) {
    try {
        return JSON.stringify(value);
    } catch {
        return '{}';
    }
}

export function truncate(value, maxLength = 90) {
    if (typeof value !== 'string') {
        return '';
    }

    if (value.length <= maxLength) {
        return value;
    }

    return `${value.slice(0, maxLength - 3)}...`;
}

export function buildGuidedJsonLogic(guided) {
    return {
        [guided.operator]: [{ var: guided.left }, guided.right],
    };
}

export function normalizeConditionLeftOperand(value) {
    return CONDITION_LEFT_OPTIONS.some((candidate) => candidate.value === value) ? value : 'trigger.value';
}

export function normalizeWindowUnit(value) {
    return WINDOW_UNITS.some((candidate) => candidate.value === value) ? value : 'minute';
}

export function createDefaultQuerySource(index = 0, existingSource = null) {
    const fallbackAlias = `source_${index + 1}`;
    const alias = typeof existingSource?.alias === 'string' && existingSource.alias.trim() !== ''
        ? existingSource.alias.trim()
        : fallbackAlias;

    return {
        alias,
        device_id: toPositiveInteger(existingSource?.device_id),
        topic_id: toPositiveInteger(existingSource?.topic_id),
        parameter_definition_id: toPositiveInteger(existingSource?.parameter_definition_id),
    };
}

export function resolveQuerySourcesDraft(sources) {
    if (!Array.isArray(sources) || sources.length === 0) {
        return [createDefaultQuerySource(0)];
    }

    return sources.map((source, index) => createDefaultQuerySource(index, isPlainObject(source) ? source : null));
}

export function parseEmailRecipientInput(value) {
    if (typeof value !== 'string') {
        return [];
    }

    const unique = {};

    value
        .split(/[\n,;]+/)
        .map((item) => item.trim())
        .filter((item) => item !== '')
        .forEach((item) => {
            unique[item.toLowerCase()] = item;
        });

    return Object.values(unique);
}

export function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

export function normalizeQueryAlias(value, fallback = 'source_1') {
    if (typeof value !== 'string') {
        return fallback;
    }

    const trimmed = value.trim().toLowerCase();

    return QUERY_ALIAS_REGEX.test(trimmed) ? trimmed : fallback;
}
