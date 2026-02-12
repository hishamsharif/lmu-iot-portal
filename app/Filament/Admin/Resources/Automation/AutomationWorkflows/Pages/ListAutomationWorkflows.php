<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Automation\AutomationWorkflows\Pages;

use App\Filament\Admin\Resources\Automation\AutomationWorkflows\AutomationWorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAutomationWorkflows extends ListRecords
{
    protected static string $resource = AutomationWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
