<?php

declare(strict_types=1);

use App\Domain\IoTDashboard\Models\IoTDashboard;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\IoTDashboards\Pages\ListIoTDashboards;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);
});

it('renders the dashboards list page', function (): void {
    livewire(ListIoTDashboards::class)
        ->assertSuccessful();
});

it('shows dashboards in the table', function (): void {
    $dashboards = IoTDashboard::factory()->count(3)->create([
        'organization_id' => Organization::factory(),
    ]);

    livewire(ListIoTDashboards::class)
        ->assertCanSeeTableRecords($dashboards);
});

it('shows an open dashboard action that opens a new tab', function (): void {
    $dashboard = IoTDashboard::factory()->create();

    livewire(ListIoTDashboards::class)
        ->assertTableActionExists(
            'openDashboard',
            fn (Action $action): bool => $action->getUrl() === route(
                'filament.admin.pages.io-t-dashboard',
                ['dashboard' => $dashboard->id],
            )
                && $action->shouldOpenUrlInNewTab(),
            $dashboard,
        );
});
