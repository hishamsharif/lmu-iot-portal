<?php

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Pages;

use App\Domain\DeviceManagement\Models\DeviceType;
use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ViewDeviceType extends ViewRecord
{
    protected static string $resource = DeviceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('onboardDevice')
                ->label('Onboard Device')
                ->icon(Heroicon::OutlinedCpuChip)
                ->url(fn (): string => DeviceResource::getUrl('create', [
                    'device_type_id' => $this->getRecord()->getKey(),
                ])),
            Actions\ReplicateAction::make()
                ->excludeAttributes(['created_at', 'updated_at'])
                ->beforeReplicaSaved(function (DeviceType $record, DeviceType $replica): void {
                    $replica->key = $this->generateReplicaKey($record);
                    $replica->name = Str::limit("{$record->name} Copy", 255, '');
                }),
            Actions\EditAction::make(),
        ];
    }

    private function generateReplicaKey(DeviceType $record): string
    {
        $base = Str::of($record->key)->lower()->append('_copy')->value();
        $candidate = Str::limit($base, 100, '');
        $counter = 2;

        while ($this->replicaKeyExists($record, $candidate)) {
            $suffix = "_{$counter}";
            $prefixLength = 100 - strlen($suffix);
            $candidate = Str::of($base)->substr(0, $prefixLength)->append($suffix)->value();
            $counter++;
        }

        return $candidate;
    }

    private function replicaKeyExists(DeviceType $record, string $key): bool
    {
        return DeviceType::query()
            ->where('key', $key)
            ->when(
                $record->organization_id === null,
                fn ($query) => $query->whereNull('organization_id'),
                fn ($query) => $query->where('organization_id', $record->organization_id),
            )
            ->exists();
    }
}
