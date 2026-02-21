import {
    CONDITION_OPERATOR_OPTIONS,
    QUERY_ALIAS_REGEX,
} from '../constants.js';
import {
    buildGuidedJsonLogic,
    compactJson,
    createDefaultQuerySource,
    isPlainObject,
    isValidEmail,
    normalizeConditionLeftOperand,
    normalizeWindowUnit,
    parseEmailRecipientInput,
    resolveQuerySourcesDraft,
    safeJsonStringify,
    toPositiveInteger,
    truncate,
} from './helpers.js';

function resolveConditionJsonLogicDraft(draft) {
    const editorTab = draft?.json_logic_editor_tab === 'advanced' ? 'advanced' : 'builder';

    if (editorTab === 'builder') {
        if (isPlainObject(draft?.json_logic_value) && Object.keys(draft.json_logic_value).length === 1) {
            return draft.json_logic_value;
        }

        if (isPlainObject(draft?.json_logic) && Object.keys(draft.json_logic).length === 1) {
            return draft.json_logic;
        }
    }

    let parsedJsonLogic;

    try {
        parsedJsonLogic = JSON.parse(typeof draft?.json_logic_text === 'string' ? draft.json_logic_text : '{}');
    } catch {
        throw new Error('Advanced JSON logic must be valid JSON.');
    }

    if (!isPlainObject(parsedJsonLogic) || Object.keys(parsedJsonLogic).length !== 1) {
        throw new Error('Advanced JSON logic must be an object with a single root operator.');
    }

    return parsedJsonLogic;
}

export function createDefaultConfigDraft(nodeType, existingConfig) {
    if (nodeType === 'telemetry-trigger') {
        const source = isPlainObject(existingConfig?.source) ? existingConfig.source : {};

        return {
            mode: 'event',
            source: {
                device_id: toPositiveInteger(source.device_id),
                topic_id: toPositiveInteger(source.topic_id),
                parameter_definition_id: toPositiveInteger(source.parameter_definition_id),
            },
        };
    }

    if (nodeType === 'condition') {
        const mode = existingConfig?.mode === 'json_logic' ? 'json_logic' : 'guided';
        const existingGuided = isPlainObject(existingConfig?.guided) ? existingConfig.guided : {};
        const guided = {
            left: normalizeConditionLeftOperand(existingGuided.left),
            operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === existingGuided.operator)
                ? existingGuided.operator
                : '>',
            right: Number.isFinite(Number(existingGuided.right)) ? Number(existingGuided.right) : 240,
        };

        const existingJsonLogic = isPlainObject(existingConfig?.json_logic)
            ? existingConfig.json_logic
            : buildGuidedJsonLogic(guided);

        return {
            mode,
            guided,
            json_logic: existingJsonLogic,
            json_logic_value: existingJsonLogic,
            json_logic_text: safeJsonStringify(existingJsonLogic),
            json_logic_editor_tab: 'builder',
        };
    }

    if (nodeType === 'query') {
        const window = isPlainObject(existingConfig?.window) ? existingConfig.window : {};
        const sources = resolveQuerySourcesDraft(existingConfig?.sources);
        const sql = typeof existingConfig?.sql === 'string' && existingConfig.sql.trim() !== ''
            ? existingConfig.sql
            : 'SELECT AVG(source_1.value) AS value FROM source_1';

        return {
            mode: 'sql',
            window: {
                size: toPositiveInteger(window.size) ?? 15,
                unit: normalizeWindowUnit(window.unit),
            },
            sources,
            sql,
        };
    }

    if (nodeType === 'alert') {
        const recipients = Array.isArray(existingConfig?.recipients)
            ? existingConfig.recipients.filter((item) => typeof item === 'string' && item.trim() !== '')
            : [];
        const cooldown = isPlainObject(existingConfig?.cooldown) ? existingConfig.cooldown : {};

        return {
            channel: 'email',
            recipients,
            recipients_text: recipients.join('\n'),
            subject: typeof existingConfig?.subject === 'string' ? existingConfig.subject : 'Automation alert',
            body: typeof existingConfig?.body === 'string'
                ? existingConfig.body
                : 'Threshold reached.\n\nRun: {{ run.id }}\nQuery value: {{ query.value }}',
            cooldown: {
                value: toPositiveInteger(cooldown.value) ?? 30,
                unit: normalizeWindowUnit(cooldown.unit),
            },
        };
    }

    if (nodeType === 'command') {
        const target = isPlainObject(existingConfig?.target) ? existingConfig.target : {};

        return {
            target: {
                device_id: toPositiveInteger(target.device_id),
                topic_id: toPositiveInteger(target.topic_id),
            },
            payload_mode: 'schema_form',
            payload: isPlainObject(existingConfig?.payload) ? { ...existingConfig.payload } : {},
        };
    }

    return {
        generic_json_text: safeJsonStringify(isPlainObject(existingConfig) ? existingConfig : {}),
    };
}

export function normalizeConfigForSave(nodeType, draft) {
    if (nodeType === 'telemetry-trigger') {
        const deviceId = toPositiveInteger(draft?.source?.device_id);
        const topicId = toPositiveInteger(draft?.source?.topic_id);
        const parameterDefinitionId = toPositiveInteger(draft?.source?.parameter_definition_id);

        if (!deviceId || !topicId || !parameterDefinitionId) {
            throw new Error('Telemetry trigger requires source device, topic, and parameter.');
        }

        return {
            mode: 'event',
            source: {
                device_id: deviceId,
                topic_id: topicId,
                parameter_definition_id: parameterDefinitionId,
            },
        };
    }

    if (nodeType === 'condition') {
        const mode = draft?.mode === 'json_logic' ? 'json_logic' : 'guided';

        if (mode === 'guided') {
            const guided = {
                left: normalizeConditionLeftOperand(draft?.guided?.left),
                operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === draft?.guided?.operator)
                    ? draft.guided.operator
                    : '>',
                right: Number(draft?.guided?.right),
            };

            if (!Number.isFinite(guided.right)) {
                throw new Error('Condition threshold must be numeric.');
            }

            return {
                mode: 'guided',
                guided,
                json_logic: buildGuidedJsonLogic(guided),
            };
        }

        const parsedJsonLogic = resolveConditionJsonLogicDraft(draft);

        return {
            mode: 'json_logic',
            guided: {
                left: normalizeConditionLeftOperand(draft?.guided?.left),
                operator: CONDITION_OPERATOR_OPTIONS.some((candidate) => candidate.value === draft?.guided?.operator)
                    ? draft.guided.operator
                    : '>',
                right: Number.isFinite(Number(draft?.guided?.right)) ? Number(draft.guided.right) : 240,
            },
            json_logic: parsedJsonLogic,
        };
    }

    if (nodeType === 'query') {
        const mode = draft?.mode === 'sql' ? 'sql' : 'sql';
        const windowSize = toPositiveInteger(draft?.window?.size);
        const windowUnit = normalizeWindowUnit(draft?.window?.unit);
        const sourceDrafts = Array.isArray(draft?.sources) ? draft.sources : [];
        const sql = typeof draft?.sql === 'string' ? draft.sql.trim() : '';

        if (!windowSize) {
            throw new Error('Query window size must be a positive number.');
        }

        if (sql === '') {
            throw new Error('Query SQL is required.');
        }

        if (sourceDrafts.length === 0) {
            throw new Error('Add at least one query source.');
        }

        const aliases = {};

        const sources = sourceDrafts.map((sourceDraft, index) => {
            const alias = typeof sourceDraft?.alias === 'string' ? sourceDraft.alias.trim() : '';
            const deviceId = toPositiveInteger(sourceDraft?.device_id);
            const topicId = toPositiveInteger(sourceDraft?.topic_id);
            const parameterDefinitionId = toPositiveInteger(sourceDraft?.parameter_definition_id);

            if (!QUERY_ALIAS_REGEX.test(alias)) {
                throw new Error(`Source #${index + 1} alias is invalid. Use letters, numbers, and underscore.`);
            }

            const normalizedAlias = alias.toLowerCase();

            if (Object.prototype.hasOwnProperty.call(aliases, normalizedAlias)) {
                throw new Error(`Source alias "${alias}" is duplicated.`);
            }

            aliases[normalizedAlias] = true;

            if (!deviceId || !topicId || !parameterDefinitionId) {
                throw new Error(`Source #${index + 1} requires device, topic, and parameter.`);
            }

            return {
                alias: normalizedAlias,
                device_id: deviceId,
                topic_id: topicId,
                parameter_definition_id: parameterDefinitionId,
            };
        });

        return {
            mode,
            window: {
                size: windowSize,
                unit: windowUnit,
            },
            sources,
            sql,
        };
    }

    if (nodeType === 'command') {
        const targetDeviceId = toPositiveInteger(draft?.target?.device_id);
        const targetTopicId = toPositiveInteger(draft?.target?.topic_id);
        const payload = isPlainObject(draft?.payload) ? draft.payload : null;

        if (!targetDeviceId || !targetTopicId) {
            throw new Error('Command node requires target device and topic.');
        }

        if (!payload) {
            throw new Error('Command payload must be an object.');
        }

        return {
            target: {
                device_id: targetDeviceId,
                topic_id: targetTopicId,
            },
            payload,
            payload_mode: 'schema_form',
        };
    }

    if (nodeType === 'alert') {
        const channel = draft?.channel === 'email' ? 'email' : 'email';
        const recipients = parseEmailRecipientInput(
            typeof draft?.recipients_text === 'string'
                ? draft.recipients_text
                : Array.isArray(draft?.recipients)
                    ? draft.recipients.join('\n')
                    : '',
        );
        const subject = typeof draft?.subject === 'string' ? draft.subject.trim() : '';
        const body = typeof draft?.body === 'string' ? draft.body.trim() : '';
        const cooldownValue = toPositiveInteger(draft?.cooldown?.value);
        const cooldownUnit = normalizeWindowUnit(draft?.cooldown?.unit);

        if (recipients.length === 0) {
            throw new Error('Alert recipients are required.');
        }

        const invalidRecipient = recipients.find((recipient) => !isValidEmail(recipient));
        if (invalidRecipient) {
            throw new Error(`Alert recipient "${invalidRecipient}" is not a valid email.`);
        }

        if (subject === '') {
            throw new Error('Alert subject is required.');
        }

        if (body === '') {
            throw new Error('Alert body is required.');
        }

        if (!cooldownValue) {
            throw new Error('Alert cooldown value must be positive.');
        }

        return {
            channel,
            recipients,
            subject,
            body,
            cooldown: {
                value: cooldownValue,
                unit: cooldownUnit,
            },
        };
    }

    let genericConfig;

    try {
        genericConfig = JSON.parse(typeof draft?.generic_json_text === 'string' ? draft.generic_json_text : '{}');
    } catch {
        throw new Error('Generic node configuration must be valid JSON.');
    }

    if (!isPlainObject(genericConfig)) {
        throw new Error('Generic node configuration must be a JSON object.');
    }

    return genericConfig;
}

export function summarizeNodeConfig(nodeType, config) {
    if (!isPlainObject(config)) {
        return '';
    }

    if (nodeType === 'telemetry-trigger') {
        const source = isPlainObject(config.source) ? config.source : {};
        const deviceId = toPositiveInteger(source.device_id);
        const topicId = toPositiveInteger(source.topic_id);
        const parameterDefinitionId = toPositiveInteger(source.parameter_definition_id);

        if (!deviceId || !topicId || !parameterDefinitionId) {
            return 'Not configured';
        }

        return `Device #${deviceId} / Topic #${topicId} / Param #${parameterDefinitionId}`;
    }

    if (nodeType === 'condition') {
        if (config.mode === 'guided' && isPlainObject(config.guided)) {
            const left = normalizeConditionLeftOperand(config.guided.left);
            const operator = typeof config.guided.operator === 'string' ? config.guided.operator : '>';
            const right = Number(config.guided.right);
            const resolvedRight = Number.isFinite(right) ? right : '?';

            return `${left} ${operator} ${resolvedRight}`;
        }

        if (isPlainObject(config.json_logic)) {
            return truncate(compactJson(config.json_logic), 84);
        }

        return 'Not configured';
    }

    if (nodeType === 'command') {
        const target = isPlainObject(config.target) ? config.target : {};
        const deviceId = toPositiveInteger(target.device_id);
        const topicId = toPositiveInteger(target.topic_id);
        const payloadKeys = isPlainObject(config.payload) ? Object.keys(config.payload) : [];
        const payloadPreview = payloadKeys.length > 0 ? payloadKeys.slice(0, 3).join(', ') : 'no payload';

        if (!deviceId || !topicId) {
            return 'Not configured';
        }

        return `Target #${deviceId} / Topic #${topicId} / ${payloadPreview}`;
    }

    if (nodeType === 'query') {
        const window = isPlainObject(config.window) ? config.window : {};
        const sources = Array.isArray(config.sources) ? config.sources : [];
        const windowSize = toPositiveInteger(window.size);
        const windowUnit = normalizeWindowUnit(window.unit);
        const sql = typeof config.sql === 'string' ? config.sql : '';

        if (!windowSize || sources.length === 0 || sql.trim() === '') {
            return 'Not configured';
        }

        return `${sources.length} source(s), ${windowSize} ${windowUnit}(s)`;
    }

    if (nodeType === 'alert') {
        const recipients = Array.isArray(config.recipients) ? config.recipients : [];
        const cooldown = isPlainObject(config.cooldown) ? config.cooldown : {};
        const cooldownValue = toPositiveInteger(cooldown.value) ?? 30;
        const cooldownUnit = normalizeWindowUnit(cooldown.unit);

        if (recipients.length === 0) {
            return 'Not configured';
        }

        return `${recipients.length} recipient(s), cooldown ${cooldownValue} ${cooldownUnit}(s)`;
    }

    const keys = Object.keys(config);

    return keys.length > 0 ? `Configured (${keys.length} keys)` : 'Not configured';
}

export { createDefaultQuerySource };
