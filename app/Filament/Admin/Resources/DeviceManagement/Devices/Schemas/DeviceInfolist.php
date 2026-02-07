<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

class DeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Device Details')
                    ->schema([
                        TextEntry::make('name')
                            ->weight('bold'),

                        TextEntry::make('uuid')
                            ->label('UUID')
                            ->copyable(),

                        TextEntry::make('external_id')
                            ->label('External ID')
                            ->placeholder('None'),

                        TextEntry::make('organization.name')
                            ->label('Organization'),

                        TextEntry::make('deviceType.name')
                            ->label('Device Type'),

                        TextEntry::make('schemaVersion.version')
                            ->label('Schema Version')
                            ->formatStateUsing(fn ($state) => "Version {$state}"),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),

                        IconEntry::make('is_simulated')
                            ->label('Simulated')
                            ->boolean(),

                        TextEntry::make('connection_state')
                            ->label('Connection State')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'online' => Color::Green,
                                'offline' => Color::Red,
                                default => Color::Gray,
                            })
                            ->placeholder('Unknown'),

                        TextEntry::make('last_seen_at')
                            ->label('Last Seen')
                            ->dateTime()
                            ->placeholder('Never'),
                    ])
                    ->columns(2),

                Section::make('Metadata')
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
