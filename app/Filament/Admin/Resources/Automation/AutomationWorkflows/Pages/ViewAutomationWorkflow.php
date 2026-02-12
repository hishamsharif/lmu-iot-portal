<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Automation\AutomationWorkflows\Pages;

use App\Filament\Admin\Resources\Automation\AutomationWorkflows\AutomationWorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewAutomationWorkflow extends ViewRecord
{
    protected static string $resource = AutomationWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dagEditor')
                ->label('DAG Editor')
                ->icon(Heroicon::OutlinedSquare3Stack3d)
                ->url(fn (): string => AutomationWorkflowResource::getUrl('dag-editor', ['record' => $this->getRecord()])),
            Actions\EditAction::make(),
        ];
    }
}
