<?php

declare(strict_types=1);

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\Reporting\Enums\ReportRunStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Jobs\GenerateReportRunJob;
use App\Domain\Reporting\Models\ReportRun;
use App\Domain\Reporting\Services\ReportGenerationService;
use App\Domain\Reporting\Services\ReportRunNotificationService;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('dispatches report notification when generation finishes with a terminal status', function (): void {
    $organization = Organization::factory()->create();
    $requestedBy = User::factory()->create();
    $device = Device::factory()->create(['organization_id' => $organization->id]);

    $reportRun = ReportRun::query()->create([
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'requested_by_user_id' => $requestedBy->id,
        'type' => ReportType::ParameterValues,
        'status' => ReportRunStatus::Queued,
        'format' => 'csv',
        'from_at' => now()->subDay(),
        'until_at' => now(),
        'timezone' => 'UTC',
    ]);

    $reportGenerationService = mock(ReportGenerationService::class, function (MockInterface $mock) use ($reportRun): void {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturnUsing(function (ReportRun $run) use ($reportRun): ReportRun {
                expect($run->is($reportRun))->toBeTrue();

                $run->forceFill([
                    'status' => ReportRunStatus::NoData,
                    'row_count' => 0,
                    'generated_at' => now(),
                ])->save();

                return $run->refresh();
            });
    });

    $reportRunNotificationService = mock(ReportRunNotificationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendForStatus')
            ->once()
            ->withArgs(fn (ReportRun $run): bool => $run->status === ReportRunStatus::NoData);
    });

    (new GenerateReportRunJob((int) $reportRun->id))
        ->handle($reportGenerationService, $reportRunNotificationService);

    expect($reportRun->refresh()->status)->toBe(ReportRunStatus::NoData);
});

it('dispatches failed report notification when generation throws', function (): void {
    $organization = Organization::factory()->create();
    $requestedBy = User::factory()->create();
    $device = Device::factory()->create(['organization_id' => $organization->id]);

    $reportRun = ReportRun::query()->create([
        'organization_id' => $organization->id,
        'device_id' => $device->id,
        'requested_by_user_id' => $requestedBy->id,
        'type' => ReportType::ParameterValues,
        'status' => ReportRunStatus::Queued,
        'format' => 'csv',
        'from_at' => now()->subDay(),
        'until_at' => now(),
        'timezone' => 'UTC',
    ]);

    $reportGenerationService = mock(ReportGenerationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('generate')
            ->once()
            ->andThrow(new RuntimeException('Queue processing failed.'));
    });

    $reportRunNotificationService = mock(ReportRunNotificationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendForStatus')
            ->once()
            ->withArgs(fn (ReportRun $run): bool => $run->status === ReportRunStatus::Failed);
    });

    (new GenerateReportRunJob((int) $reportRun->id))
        ->handle($reportGenerationService, $reportRunNotificationService);

    $reportRun->refresh();

    expect($reportRun->status)->toBe(ReportRunStatus::Failed)
        ->and($reportRun->failure_reason)->toContain('Queue processing failed.');
});
