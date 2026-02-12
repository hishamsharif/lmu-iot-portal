<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Automation\AutomationWorkflows\Pages;

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Filament\Admin\Resources\Automation\AutomationWorkflows\AutomationWorkflowResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateAutomationWorkflow extends CreateRecord
{
    protected static string $resource = AutomationWorkflowResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $slugSource = trim($this->resolveStringValue($data['slug'] ?? null));
        if ($slugSource === '') {
            $slugSource = $this->resolveStringValue($data['name'] ?? null);
        }

        $userId = auth()->id();

        $resolvedStatus = $data['status'] ?? AutomationWorkflowStatus::Draft;
        if ($resolvedStatus instanceof AutomationWorkflowStatus) {
            $resolvedStatus = $resolvedStatus->value;
        }

        $data['slug'] = Str::slug($slugSource);
        $data['status'] = is_string($resolvedStatus) && $resolvedStatus !== ''
            ? $resolvedStatus
            : AutomationWorkflowStatus::Draft->value;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return AutomationWorkflowResource::getUrl('dag-editor', ['record' => $this->getRecord()]);
    }

    private function resolveStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }
}
