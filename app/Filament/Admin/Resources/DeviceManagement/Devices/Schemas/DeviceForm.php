<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Schemas;

use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('uuid')
                            ->label('UUID')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),

                        TextInput::make('external_id')
                            ->label('External ID')
                            ->maxLength(255)
                            ->helperText('Product serial number or hardware ID'),
                    ])
                    ->columns(2),

                Section::make('Configuration')
                    ->schema([
                        Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('device_type_id')
                            ->label('Device Type')
                            ->relationship('deviceType', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Select::make('device_schema_version_id')
                            ->label('Schema Version')
                            ->options(fn (Get $get) => DeviceSchemaVersion::query()
                                ->whereHas('schema', fn ($query) => $query->where('device_type_id', $get('device_type_id')))
                                ->where('status', 'active')
                                ->pluck('version', 'id')
                                ->map(fn (mixed $version): string => 'Version '.(is_scalar($version) ? (string) $version : ''))
                                ->toArray())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get) => ! $get('device_type_id'))
                            ->helperText('Only active schema versions for the selected device type are shown'),
                    ])
                    ->columns(2),

                Section::make('Status & Simulation')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true),

                        Toggle::make('is_simulated')
                            ->label('Is Simulated')
                            ->default(false),

                        TextInput::make('connection_state')
                            ->label('Connection State')
                            ->disabled()
                            ->placeholder('Unknown'),

                        TextInput::make('last_seen_at')
                            ->label('Last Seen At')
                            ->disabled()
                            ->placeholder('Never'),
                    ])
                    ->columns(2),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
