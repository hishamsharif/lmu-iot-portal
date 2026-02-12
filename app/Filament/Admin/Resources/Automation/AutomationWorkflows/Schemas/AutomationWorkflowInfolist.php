<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Automation\AutomationWorkflows\Schemas;

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Domain\Automation\Models\AutomationWorkflow;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AutomationWorkflowInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Workflow')
                    ->schema([
                        TextEntry::make('name')
                            ->weight('medium'),

                        TextEntry::make('slug')
                            ->copyable(),

                        TextEntry::make('organization.name')
                            ->label('Organization'),

                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state))
                            ->color(fn (mixed $state): string => self::statusColor($state)),

                        TextEntry::make('activeVersion.version')
                            ->label('Active Version')
                            ->state(fn (AutomationWorkflow $record): ?int => $record->activeVersion?->version)
                            ->formatStateUsing(fn (mixed $state): string => is_scalar($state) ? "v{$state}" : 'No version'),
                    ])
                    ->columns(2),

                Section::make('Usage')
                    ->schema([
                        TextEntry::make('versions_count')
                            ->label('Versions')
                            ->state(fn (AutomationWorkflow $record): int => $record->versions()->count()),

                        TextEntry::make('runs_count')
                            ->label('Runs')
                            ->state(fn (AutomationWorkflow $record): int => $record->runs()->count()),
                    ])
                    ->columns(2),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    private static function statusColor(mixed $state): string
    {
        return match (self::statusValue($state)) {
            AutomationWorkflowStatus::Active->value => 'success',
            AutomationWorkflowStatus::Paused->value => 'warning',
            AutomationWorkflowStatus::Archived->value => 'gray',
            default => 'info',
        };
    }

    private static function statusLabel(mixed $state): string
    {
        return Str::headline(self::statusValue($state));
    }

    private static function statusValue(mixed $state): string
    {
        if ($state instanceof AutomationWorkflowStatus) {
            return $state->value;
        }

        return is_string($state) ? $state : AutomationWorkflowStatus::Draft->value;
    }
}
