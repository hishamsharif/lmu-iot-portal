<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Schemas;

use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DeviceSchemaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schema')
                    ->schema([
                        TextEntry::make('name')
                            ->weight('medium'),
                        TextEntry::make('deviceType.name')
                            ->label('Device Type')
                            ->icon(Heroicon::OutlinedCpuChip)
                            ->url(fn (DeviceSchema $record): ?string => $record->device_type_id
                                ? DeviceTypeResource::getUrl('view', ['record' => $record->device_type_id])
                                : null),
                    ])
                    ->columns(2),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedClock),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedClock),
                    ])
                    ->columns(2),
            ]);
    }
}
