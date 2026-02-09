<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Tables;

use App\Domain\DeviceManagement\Models\Device;
use App\Filament\Actions\DeviceManagement\ReplicateDeviceActions;
use App\Filament\Actions\DeviceManagement\SimulatePublishingActions;
use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use App\Filament\Admin\Resources\Shared\Organizations\OrganizationResource;
use Filament\Actions;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\TextInput;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record) => $record->uuid),

                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Device $record): ?string => $record->organization_id
                        ? OrganizationResource::getUrl('view', ['record' => $record->organization_id])
                        : null),

                TextColumn::make('deviceType.name')
                    ->label('Type')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Device $record): ?string => $record->device_type_id
                        ? DeviceTypeResource::getUrl('view', ['record' => $record->device_type_id])
                        : null),

                TextColumn::make('schemaVersion.version')
                    ->label('Schema')
                    ->formatStateUsing(fn (mixed $state): string => is_scalar($state) ? "v{$state}" : '—')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_simulated')
                    ->label('Simulated')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('connection_state')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'online' => Color::Green,
                        'offline' => Color::Red,
                        default => Color::Gray,
                    })
                    ->sortable(),

                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('external_id')
                    ->label('External ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

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

                SelectFilter::make('deviceType')
                    ->relationship('deviceType', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('connection_state')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                    ]),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\Action::make('viewFirmware')
                    ->label('Firmware')
                    ->icon(Heroicon::OutlinedCodeBracketSquare)
                    ->modalHeading('Rendered Firmware')
                    ->modalWidth('7xl')
                    ->slideOver()
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->fillForm(fn (Device $record): array => [
                        'filename' => $record->schemaVersion?->firmware_filename ?: 'firmware.ino',
                        'firmware' => $record->schemaVersion?->renderFirmwareForDevice($record)
                            ?? '// No firmware template is configured for this device schema version.',
                    ])
                    ->form([
                        TextInput::make('filename')
                            ->label('File Name')
                            ->disabled()
                            ->dehydrated(false),
                        CodeEditor::make('firmware')
                            ->label('Firmware')
                            ->language(Language::Cpp)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
                Actions\Action::make('controlDashboard')
                    ->label('Control')
                    ->icon(Heroicon::OutlinedCommandLine)
                    ->url(fn (Device $record): string => DeviceResource::getUrl('control-dashboard', ['record' => $record])),
                SimulatePublishingActions::recordAction(),
                Actions\EditAction::make(),
                ReplicateDeviceActions::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    SimulatePublishingActions::bulkAction(),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
