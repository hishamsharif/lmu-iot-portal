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
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\RelationManagers\DerivedParameterDefinitionsRelationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->user);

    DerivedParameterDefinitionsRelationManager::skipAuthorization();
});

afterEach(function (): void {
    DerivedParameterDefinitionsRelationManager::skipAuthorization(false);
});

it('validates dependencies against expression variables', function (): void {
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

    ParameterDefinition::factory()->create([
        'schema_version_topic_id' => $topic->id,
        'key' => 'humidity',
        'type' => ParameterDataType::Decimal,
    ]);

    livewire(DerivedParameterDefinitionsRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditDeviceSchemaVersion::class,
    ])
        ->callTableAction('create', data: [
            'key' => 'heat_index',
            'label' => 'Heat Index',
            'data_type' => ParameterDataType::Decimal->value,
            'dependencies' => ['temp_c'],
            'expression' => json_encode([
                '+' => [
                    ['var' => 'temp_c'],
                    ['var' => 'humidity'],
                ],
            ]),
        ])
        ->assertHasFormErrors(['expression']);
});
