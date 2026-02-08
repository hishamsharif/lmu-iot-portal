<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Jobs\SimulateDevicePublishingJob;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\DeviceManagement\Devices\Pages\ListDevices;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

it('queues simulation jobs for selected devices', function (): void {
    Queue::fake();

    $schemaVersion = DeviceSchemaVersion::factory()->create();

    SchemaVersionTopic::factory()->publish()->create([
        'device_schema_version_id' => $schemaVersion->id,
        'suffix' => 'telemetry',
    ]);

    $devices = Device::factory()->count(2)->create([
        'device_schema_version_id' => $schemaVersion->id,
    ]);

    livewire(ListDevices::class)
        ->selectTableRecords($devices->pluck('id')->all())
        ->callAction(TestAction::make('simulatePublishingBulk')->table()->bulk(), data: [
            'count' => 5,
            'interval' => 1,
        ]);

    Queue::assertPushed(SimulateDevicePublishingJob::class, 2);
});
