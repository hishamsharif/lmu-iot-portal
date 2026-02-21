export type TelemetryTriggerNodeConfig = {
    mode: 'event';
    source: {
        device_id: number;
        topic_id: number;
        parameter_definition_id: number;
    };
};

export type ConditionGuidedConfig = {
    left: 'trigger.value' | 'query.value';
    operator: '>' | '>=' | '<' | '<=' | '==' | '!=';
    right: number;
};

export type JsonLogicOperator =
    | 'var'
    | 'if'
    | 'and'
    | 'or'
    | '!'
    | '!!'
    | '=='
    | '==='
    | '!='
    | '!=='
    | '>'
    | '>='
    | '<'
    | '<='
    | '+'
    | '-'
    | '*'
    | '/'
    | 'min'
    | 'max'
    | 'missing'
    | 'missing_some';

export type JsonLogicValue =
    | string
    | number
    | boolean
    | null
    | JsonLogicExpression
    | JsonLogicValue[];

export type JsonLogicExpression = {
    [Operator in JsonLogicOperator]?: Operator extends 'var'
        ? string | [string] | [string, JsonLogicValue]
        : Operator extends 'missing'
            ? string[]
            : Operator extends 'missing_some'
                ? [number, string[]]
                : JsonLogicValue | JsonLogicValue[];
};

export type ConditionNodeConfig = {
    mode: 'guided' | 'json_logic';
    json_logic: JsonLogicExpression;
    guided?: ConditionGuidedConfig;
};

export type CommandNodeConfig = {
    target: {
        device_id: number;
        topic_id: number;
    };
    payload: Record<string, unknown>;
    payload_mode: 'schema_form';
};

export type QueryNodeConfig = {
    mode: 'sql';
    window: {
        size: number;
        unit: 'minute' | 'hour' | 'day';
    };
    sources: Array<{
        alias: string;
        device_id: number;
        topic_id: number;
        parameter_definition_id: number;
    }>;
    sql: string;
};

export type AlertNodeConfig = {
    channel: 'email';
    recipients: string[];
    subject: string;
    body: string;
    cooldown: {
        value: number;
        unit: 'minute' | 'hour' | 'day';
    };
};

export type GenericNodeConfig = Record<string, unknown>;

export type WorkflowNodeData = {
    label?: string;
    summary?: string;
    nodeType?: string;
    config?:
        | TelemetryTriggerNodeConfig
        | ConditionNodeConfig
        | CommandNodeConfig
        | QueryNodeConfig
        | AlertNodeConfig
        | GenericNodeConfig;
    [key: string]: unknown;
};

export type WorkflowNode = {
    id: string;
    type: string;
    data?: WorkflowNodeData;
    position?: {
        x: number;
        y: number;
    };
};

export type WorkflowEdge = {
    id?: string;
    source: string;
    target: string;
    type?: string;
};

export type WorkflowGraph = {
    version: number;
    nodes: WorkflowNode[];
    edges: WorkflowEdge[];
    viewport?: Record<string, unknown>;
};
