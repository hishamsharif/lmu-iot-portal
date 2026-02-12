<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Automation\AutomationWorkflows\Tables;

use App\Domain\Automation\Enums\AutomationWorkflowStatus;
use App\Domain\Automation\Models\AutomationWorkflow;
use App\Filament\Admin\Resources\Automation\AutomationWorkflows\AutomationWorkflowResource;
use App\Filament\Admin\Resources\Shared\Organizations\OrganizationResource;
use Filament\Actions;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AutomationWorkflowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (AutomationWorkflow $record): string => $record->slug),

                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable()
                    ->url(fn (AutomationWorkflow $record): ?string => $record->organization_id
                        ? OrganizationResource::getUrl('view', ['record' => $record->organization_id])
                        : null),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state))
                    ->color(fn (mixed $state): string => self::statusColor($state))
                    ->sortable(),

                TextColumn::make('activeVersion.version')
                    ->label('Active Version')
                    ->formatStateUsing(fn (mixed $state): string => is_scalar($state) ? "v{$state}" : '—')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('organization')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->recordUrl(fn (AutomationWorkflow $record): string => AutomationWorkflowResource::getUrl('dag-editor', ['record' => $record]))
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\Action::make('dagEditor')
                        ->label('DAG Editor')
                        ->icon(Heroicon::OutlinedSquare3Stack3d)
                        ->url(fn (AutomationWorkflow $record): string => AutomationWorkflowResource::getUrl('dag-editor', ['record' => $record])),
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon(Heroicon::OutlinedEllipsisVertical),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (AutomationWorkflowStatus::cases() as $status) {
            $options[$status->value] = Str::headline($status->name);
        }

        return $options;
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
