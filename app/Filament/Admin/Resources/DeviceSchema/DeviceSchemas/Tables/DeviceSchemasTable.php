<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemas\Tables;

use App\Domain\DeviceSchema\Models\DeviceSchema;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceSchemasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deviceType.name')
                    ->label('Device Type')
                    ->searchable()
                    ->sortable()
                    ->url(fn (DeviceSchema $record): ?string => $record->device_type_id
                        ? DeviceTypeResource::getUrl('view', ['record' => $record->device_type_id])
                        : null),

                TextColumn::make('versions_count')
                    ->label('Versions')
                    ->counts('versions')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
