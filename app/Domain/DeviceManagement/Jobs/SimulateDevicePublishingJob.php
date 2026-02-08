<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Jobs;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\DevicePublishingSimulator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SimulateDevicePublishingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $deviceId,
        public int $count = 10,
        public int $intervalSeconds = 1,
        public ?int $schemaVersionTopicId = null,
    ) {
        $this->onConnection('redis-simulations');
        $this->onQueue('simulations');
    }

    public function handle(DevicePublishingSimulator $simulator): void
    {
        $device = Device::query()->find($this->deviceId);

        if (! $device instanceof Device) {
            return;
        }

        $simulator->simulate(
            device: $device,
            count: $this->count,
            intervalSeconds: $this->intervalSeconds,
            schemaVersionTopicId: $this->schemaVersionTopicId,
        );
    }
}
