<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Domain\Authorization\Models{
/**
 * @property string $name
 * @property int $organization_id
 * @property Organization|null $organization
 * @property int $id
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Shared\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\RoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 */
	class Role extends \Eloquent {}
}

namespace App\Domain\DeviceControl\Models{
/**
 * @property int $id
 * @property int $device_id
 * @property int $schema_version_topic_id
 * @property int|null $user_id
 * @property array<array-key, mixed> $command_payload
 * @property \App\Domain\DeviceControl\Enums\CommandStatus $status
 * @property array<array-key, mixed>|null $response_payload
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domain\DeviceManagement\Models\Device $device
 * @property-read \App\Domain\DeviceSchema\Models\SchemaVersionTopic $topic
 * @property-read \App\Domain\Shared\Models\User|null $user
 * @method static \Database\Factories\Domain\DeviceControl\Models\DeviceCommandLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereAcknowledgedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereCommandPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereResponsePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereSchemaVersionTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCommandLog whereUserId($value)
 */
	class DeviceCommandLog extends \Eloquent {}
}

namespace App\Domain\DeviceControl\Models{
/**
 * @property int $id
 * @property int $device_id
 * @property array<array-key, mixed> $desired_state
 * @property \Illuminate\Support\Carbon|null $reconciled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domain\DeviceManagement\Models\Device $device
 * @method static \Database\Factories\Domain\DeviceControl\Models\DeviceDesiredStateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState whereDesiredState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState whereReconciledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceDesiredState whereUpdatedAt($value)
 */
	class DeviceDesiredState extends \Eloquent {}
}

namespace App\Domain\DeviceManagement\Models{
/**
 * @property int $id
 * @property int $organization_id
 * @property int $device_type_id
 * @property int $device_schema_version_id
 * @property string $uuid
 * @property string $name
 * @property string|null $external_id
 * @property array<array-key, mixed>|null $metadata
 * @property bool $is_active
 * @property bool $is_simulated
 * @property string|null $connection_state
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\DeviceControl\Models\DeviceCommandLog> $commandLogs
 * @property-read int|null $command_logs_count
 * @property-read \App\Domain\DeviceControl\Models\DeviceDesiredState|null $desiredState
 * @property-read \App\Domain\DeviceManagement\Models\DeviceType $deviceType
 * @property-read \App\Domain\Shared\Models\Organization $organization
 * @property-read \App\Domain\DeviceSchema\Models\DeviceSchemaVersion $schemaVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Telemetry\Models\DeviceTelemetryLog> $telemetryLogs
 * @property-read int|null $telemetry_logs_count
 * @method static \Database\Factories\Domain\DeviceManagement\Models\DeviceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereConnectionState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDeviceSchemaVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDeviceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereIsSimulated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device withoutTrashed()
 */
	class Device extends \Eloquent {}
}

namespace App\Domain\DeviceManagement\Models{
/**
 * @property ProtocolConfigInterface|null $protocol_config
 * @property ProtocolType $default_protocol
 * @property int $id
 * @property int|null $organization_id
 * @property string $key
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domain\Shared\Models\Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\DeviceSchema\Models\DeviceSchema> $schemas
 * @property-read int|null $schemas_count
 * @method static \Database\Factories\Domain\DeviceManagement\Models\DeviceTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType forOrganization(\App\Domain\Shared\Models\Organization|int $organization)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType global()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType whereDefaultProtocol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType whereProtocolConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceType whereUpdatedAt($value)
 */
	class DeviceType extends \Eloquent {}
}

namespace App\Domain\DeviceSchema\Models{
/**
 * @property int $id
 * @property int $device_schema_version_id
 * @property string $key
 * @property string $label
 * @property \App\Domain\DeviceSchema\Enums\ParameterDataType $data_type
 * @property string|null $unit
 * @property array<array-key, mixed> $expression
 * @property array<array-key, mixed>|null $dependencies
 * @property string|null $json_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domain\DeviceSchema\Models\DeviceSchemaVersion $schemaVersion
 * @method static \Database\Factories\Domain\DeviceSchema\Models\DerivedParameterDefinitionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereDataType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereDependencies($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereDeviceSchemaVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereExpression($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereJsonPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DerivedParameterDefinition whereUpdatedAt($value)
 */
	class DerivedParameterDefinition extends \Eloquent {}
}

namespace App\Domain\DeviceSchema\Models{
/**
 * @property int $id
 * @property int $device_type_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Domain\DeviceManagement\Models\DeviceType $deviceType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\DeviceSchema\Models\DeviceSchemaVersion> $versions
 * @property-read int|null $versions_count
 * @method static \Database\Factories\Domain\DeviceSchema\Models\DeviceSchemaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema whereDeviceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchema withoutTrashed()
 */
	class DeviceSchema extends \Eloquent {}
}

namespace App\Domain\DeviceSchema\Models{
/**
 * @property int $id
 * @property int $device_schema_id
 * @property int $version
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\DeviceSchema\Models\DerivedParameterDefinition> $derivedParameters
 * @property-read int|null $derived_parameters_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\DeviceSchema\Models\ParameterDefinition> $parameters
 * @property-read int|null $parameters_count
 * @property-read \App\Domain\DeviceSchema\Models\DeviceSchema $schema
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Telemetry\Models\DeviceTelemetryLog> $telemetryLogs
 * @property-read int|null $telemetry_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\DeviceSchema\Models\SchemaVersionTopic> $topics
 * @property-read int|null $topics_count
 * @method static \Database\Factories\Domain\DeviceSchema\Models\DeviceSchemaVersionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion whereDeviceSchemaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceSchemaVersion whereVersion($value)
 */
	class DeviceSchemaVersion extends \Eloquent {}
}

namespace App\Domain\DeviceSchema\Models{
/**
 * @property ParameterDataType $type
 * @property int $id
 * @property int $schema_version_topic_id
 * @property string $key
 * @property string $label
 * @property string $json_path
 * @property string|null $unit
 * @property bool $required
 * @property bool $is_critical
 * @property array<array-key, mixed>|null $validation_rules
 * @property string|null $validation_error_code
 * @property array<array-key, mixed>|null $mutation_expression
 * @property int $sequence
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed>|null $default_value
 * @property-read \App\Domain\DeviceSchema\Models\SchemaVersionTopic $topic
 * @method static \Database\Factories\Domain\DeviceSchema\Models\ParameterDefinitionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereDefaultValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereIsCritical($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereJsonPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereMutationExpression($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereSchemaVersionTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereValidationErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParameterDefinition whereValidationRules($value)
 */
	class ParameterDefinition extends \Eloquent {}
}

namespace App\Domain\DeviceSchema\Models{
/**
 * @property TopicDirection $direction
 * @property int $id
 * @property int $device_schema_version_id
 * @property string $key
 * @property string $label
 * @property string $suffix
 * @property string|null $description
 * @property int $qos
 * @property bool $retain
 * @property int $sequence
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\DeviceControl\Models\DeviceCommandLog> $commandLogs
 * @property-read int|null $command_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\DeviceSchema\Models\ParameterDefinition> $parameters
 * @property-read int|null $parameters_count
 * @property-read \App\Domain\DeviceSchema\Models\DeviceSchemaVersion $schemaVersion
 * @method static \Database\Factories\Domain\DeviceSchema\Models\SchemaVersionTopicFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereDeviceSchemaVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereQos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereRetain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereSuffix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchemaVersionTopic whereUpdatedAt($value)
 */
	class SchemaVersionTopic extends \Eloquent {}
}

namespace App\Domain\Shared\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $logo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Authorization\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Shared\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\OrganizationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization withoutTrashed()
 */
	class Organization extends \Eloquent implements \Filament\Models\Contracts\HasAvatar {}
}

namespace App\Domain\Shared\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_super_admin
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Shared\Models\Organization> $organizations
 * @property-read int|null $organizations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domain\Authorization\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsSuperAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 */
	class User extends \Eloquent implements \Filament\Models\Contracts\FilamentUser, \Filament\Models\Contracts\HasTenants {}
}

namespace App\Domain\Telemetry\Models{
/**
 * @property string $id
 * @property int $device_id
 * @property int $device_schema_version_id
 * @property int|null $schema_version_topic_id
 * @property \App\Domain\Telemetry\Enums\ValidationStatus $validation_status
 * @property array<array-key, mixed> $raw_payload
 * @property array<array-key, mixed> $transformed_values
 * @property \Illuminate\Support\Carbon $recorded_at
 * @property \Illuminate\Support\Carbon $received_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domain\DeviceManagement\Models\Device $device
 * @property-read \App\Domain\DeviceSchema\Models\DeviceSchemaVersion $schemaVersion
 * @property-read \App\Domain\DeviceSchema\Models\SchemaVersionTopic|null $topic
 * @method static \Database\Factories\Domain\Telemetry\Models\DeviceTelemetryLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereDeviceSchemaVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereRawPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereRecordedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereSchemaVersionTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereTransformedValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceTelemetryLog whereValidationStatus($value)
 */
	class DeviceTelemetryLog extends \Eloquent {}
}

