export const NODE_PALETTE = [
    { type: 'telemetry-trigger', label: 'Telemetry Trigger' },
    { type: 'schedule-trigger', label: 'Schedule Trigger' },
    { type: 'condition', label: 'Condition' },
    { type: 'query', label: 'Query' },
    { type: 'delay', label: 'Delay' },
    { type: 'command', label: 'Command' },
    { type: 'alert', label: 'Alert' },
];

export const CONDITION_LEFT_OPTIONS = [
    { value: 'trigger.value', label: 'Trigger Value' },
    { value: 'query.value', label: 'Query Value' },
];

export const CONDITION_OPERATOR_OPTIONS = [
    { value: '>', label: 'Greater than' },
    { value: '>=', label: 'Greater than or equal' },
    { value: '<', label: 'Less than' },
    { value: '<=', label: 'Less than or equal' },
    { value: '==', label: 'Equal to' },
    { value: '!=', label: 'Not equal to' },
];

export const JSON_LOGIC_OPERATOR_DEFINITIONS = [
    {
        value: 'and',
        label: 'All conditions (and)',
        arity: 'variadic',
        minArgs: 2,
    },
    {
        value: 'or',
        label: 'Any condition (or)',
        arity: 'variadic',
        minArgs: 2,
    },
    {
        value: '!',
        label: 'Not (!)',
        arity: 'fixed',
        minArgs: 1,
        maxArgs: 1,
    },
    {
        value: '!!',
        label: 'Truthy (!!)',
        arity: 'fixed',
        minArgs: 1,
        maxArgs: 1,
    },
    {
        value: 'if',
        label: 'If / Else',
        arity: 'fixed',
        minArgs: 3,
        maxArgs: 3,
    },
    {
        value: '==',
        label: 'Equal (==)',
        arity: 'fixed',
        minArgs: 2,
        maxArgs: 2,
    },
    {
        value: '===',
        label: 'Strict Equal (===)',
        arity: 'fixed',
        minArgs: 2,
        maxArgs: 2,
    },
    {
        value: '!=',
        label: 'Not Equal (!=)',
        arity: 'fixed',
        minArgs: 2,
        maxArgs: 2,
    },
    {
        value: '!==',
        label: 'Strict Not Equal (!==)',
        arity: 'fixed',
        minArgs: 2,
        maxArgs: 2,
    },
    {
        value: '>',
        label: 'Greater Than (>)',
        arity: 'fixed',
        minArgs: 2,
        maxArgs: 2,
    },
    {
        value: '>=',
        label: 'Greater Than or Equal (>=)',
        arity: 'fixed',
        minArgs: 2,
        maxArgs: 2,
    },
    {
        value: '<',
        label: 'Less Than (<)',
        arity: 'fixed',
        minArgs: 2,
        maxArgs: 2,
    },
    {
        value: '<=',
        label: 'Less Than or Equal (<=)',
        arity: 'fixed',
        minArgs: 2,
        maxArgs: 2,
    },
    {
        value: '+',
        label: 'Add (+)',
        arity: 'variadic',
        minArgs: 2,
    },
    {
        value: '-',
        label: 'Subtract (-)',
        arity: 'variadic',
        minArgs: 2,
    },
    {
        value: '*',
        label: 'Multiply (*)',
        arity: 'variadic',
        minArgs: 2,
    },
    {
        value: '/',
        label: 'Divide (/)',
        arity: 'variadic',
        minArgs: 2,
    },
    {
        value: 'min',
        label: 'Minimum',
        arity: 'variadic',
        minArgs: 2,
    },
    {
        value: 'max',
        label: 'Maximum',
        arity: 'variadic',
        minArgs: 2,
    },
    {
        value: 'missing',
        label: 'Missing Fields',
        arity: 'custom',
        minArgs: 1,
    },
    {
        value: 'missing_some',
        label: 'Missing Some Fields',
        arity: 'custom',
        minArgs: 2,
    },
    {
        value: 'var',
        label: 'Variable',
        arity: 'custom',
        minArgs: 1,
    },
];

export const DEFAULT_VIEWPORT = { x: 0, y: 0, zoom: 1 };

export const WINDOW_UNITS = [
    { value: 'minute', label: 'Minutes' },
    { value: 'hour', label: 'Hours' },
    { value: 'day', label: 'Days' },
];

export const QUERY_ALIAS_REGEX = /^[a-z][a-z0-9_]{0,30}$/i;
