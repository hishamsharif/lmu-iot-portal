<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\Pages\EditDeviceSchemaVersion;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\RelationManagers\ParameterDefinitionsRelationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);

    ParameterDefinitionsRelationManager::skipAuthorization();
});

afterEach(function (): void {
    ParameterDefinitionsRelationManager::skipAuthorization(false);
});

it('validates unique key per schema version topic', function (): void {
    $deviceType = DeviceType::factory()->mqtt()->create();
    $schema = DeviceSchema::factory()->forDeviceType($deviceType)->create();
    $version = DeviceSchemaVersion::factory()->create([
        'device_schema_id' => $schema->id,
    ]);

    $topic = SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $version->id,
    ]);

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'temp_c',
        'type' => ParameterDataType::Decimal,
    ]);

    livewire(ParameterDefinitionsRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditDeviceSchemaVersion::class,
    ])
        ->callTableAction('create', data: [
            'schema_version_topic_id' => $topic->id,
            'key' => 'temp_c',
            'label' => 'Temp',
            'json_path' => 'temp_c',
            'type' => ParameterDataType::Decimal->value,
            'required' => true,
            'is_critical' => false,
            'sequence' => 1,
            'is_active' => true,
        ])
        ->assertHasFormErrors(['key' => 'unique']);
});
