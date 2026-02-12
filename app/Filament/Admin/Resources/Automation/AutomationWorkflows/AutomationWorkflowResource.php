<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Automation\AutomationWorkflows;

use App\Domain\Automation\Models\AutomationWorkflow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AutomationWorkflowResource extends Resource
{
    protected static ?string $model = AutomationWorkflow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('Automation');
    }

    public static function getNavigationLabel(): string
    {
        return __('Automations');
    }

    public static function form(Schema $schema): Schema
    {
        return Schemas\AutomationWorkflowForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\AutomationWorkflowInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\AutomationWorkflowsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutomationWorkflows::route('/'),
            'create' => Pages\CreateAutomationWorkflow::route('/create'),
            'view' => Pages\ViewAutomationWorkflow::route('/{record}'),
            'edit' => Pages\EditAutomationWorkflow::route('/{record}/edit'),
            'dag-editor' => Pages\EditAutomationDag::route('/{record}/dag-editor'),
        ];
    }
}
