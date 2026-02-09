<?php

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Pages;

use App\Domain\DeviceManagement\Models\Device;
use App\Filament\Actions\DeviceManagement\ReplicateDeviceActions;
use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use Filament\Actions;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('controlDashboard')
                ->label('Control Dashboard')
                ->icon(Heroicon::OutlinedCommandLine)
                ->url(fn (): string => DeviceResource::getUrl('control-dashboard', ['record' => $this->record])),
            Actions\Action::make('viewFirmware')
                ->label('View Firmware')
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
            ReplicateDeviceActions::make(),
            Actions\EditAction::make(),
        ];
    }
}
