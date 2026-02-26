<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Pages;

use App\Domain\DeviceManagement\Models\Device;
use App\Filament\Actions\DeviceManagement\ProvisionX509CertificateAction;
use App\Filament\Actions\DeviceManagement\ReplicateDeviceActions;
use App\Filament\Actions\DeviceManagement\RevokeX509CertificateAction;
use App\Filament\Actions\DeviceManagement\RotateX509CertificateAction;
use App\Filament\Actions\DeviceManagement\ViewFirmwareAction;
use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('controlDashboard')
                    ->label('Control Dashboard')
                    ->icon(Heroicon::OutlinedCommandLine)
                    ->url(fn (): string => DeviceResource::getUrl('control-dashboard', ['record' => $this->record]))
                    ->visible(fn (): bool => $this->record instanceof Device && $this->record->canBeControlled()),
                ViewFirmwareAction::make(),
                ProvisionX509CertificateAction::make(),
                RotateX509CertificateAction::make(),
                RevokeX509CertificateAction::make(),
                ReplicateDeviceActions::make(),
                Actions\EditAction::make(),
            ])
                ->label('Actions')
                ->icon(Heroicon::OutlinedEllipsisVertical),
        ];
    }
}
