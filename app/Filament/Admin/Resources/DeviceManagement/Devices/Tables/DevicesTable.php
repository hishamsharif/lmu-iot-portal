<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Tables;

use App\Domain\DeviceManagement\Models\Device;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use App\Filament\Admin\Resources\Shared\Organizations\OrganizationResource;
use Filament\Actions;
use Filament\Support\Colors\Color;
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
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
