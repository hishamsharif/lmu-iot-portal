<?php

declare(strict_types=1);

use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Automation\AutomationWorkflows\Pages\ListAutomationWorkflows;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

it('can render the automation workflows list page', function (): void {
    livewire(ListAutomationWorkflows::class)
        ->assertSuccessful();
});

it('can see automation workflows in the table', function (): void {
    $workflows = AutomationWorkflow::factory()->count(3)->create();

    livewire(ListAutomationWorkflows::class)
        ->assertCanSeeTableRecords($workflows);
});

it('shows a dag editor action for each workflow', function (): void {
    $workflow = AutomationWorkflow::factory()->create();

    livewire(ListAutomationWorkflows::class)
        ->assertTableActionExists(
            'dagEditor',
            fn (Action $action): bool => $action->getLabel() === 'DAG Editor',
            $workflow,
        );
});
