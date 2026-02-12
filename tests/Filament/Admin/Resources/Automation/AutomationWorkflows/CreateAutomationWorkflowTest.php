<?php

declare(strict_types=1);

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use App\Filament\Admin\Resources\Automation\AutomationWorkflows\AutomationWorkflowResource;
use App\Filament\Admin\Resources\Automation\AutomationWorkflows\Pages\CreateAutomationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

it('creates an automation workflow and redirects to the dag editor', function (): void {
    $organization = Organization::factory()->create();

    $component = livewire(CreateAutomationWorkflow::class)
        ->fillForm([
            'organization_id' => $organization->id,
            'name' => 'Cooling Alert Workflow',
            'slug' => 'Cooling Alert Workflow',
            'status' => AutomationWorkflowStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $workflow = AutomationWorkflow::query()->firstOrFail();

    $component->assertRedirect(AutomationWorkflowResource::getUrl('dag-editor', ['record' => $workflow]));

    expect($workflow->slug)->toBe('cooling-alert-workflow')
        ->and($workflow->status)->toBe(AutomationWorkflowStatus::Draft)
        ->and($workflow->organization_id)->toBe($organization->id)
        ->and($workflow->created_by)->toBe($this->admin->id)
        ->and($workflow->updated_by)->toBe($this->admin->id);
});
