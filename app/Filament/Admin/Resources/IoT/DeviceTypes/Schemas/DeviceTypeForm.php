<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceTypes\Schemas;

use App\Domain\IoT\Enums\HttpAuthType;
use App\Domain\IoT\Enums\ProtocolType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DeviceTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->regex('/^[a-z0-9_]+$/')
                            ->helperText('Unique identifier (lowercase letters, numbers, and underscores only)'),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable name for this device type'),

                        Select::make('default_protocol')
                            ->label('Protocol Type')
                            ->options(ProtocolType::class)
                            ->required()
                            ->live()
                            ->helperText('Communication protocol used by this device type'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Protocol Configuration')
                    ->schema([
                        // MQTT Configuration
                        Grid::make(2)
                            ->schema([
                                TextInput::make('protocol_config.broker_host')
                                    ->label('Broker Host')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('protocol_config.broker_port')
                                    ->label('Broker Port')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(65535)
                                    ->default(1883),

                                TextInput::make('protocol_config.username')
                                    ->label('Username')
                                    ->maxLength(255),

                                TextInput::make('protocol_config.password')
                                    ->label('Password')
                                    ->password()
                                    ->maxLength(255),

                                Checkbox::make('protocol_config.use_tls')
                                    ->label('Use TLS/SSL')
                                    ->default(false),

                                TextInput::make('protocol_config.telemetry_topic_template')
                                    ->label('Telemetry Topic Template')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('devices/{device_id}/telemetry')
                                    ->helperText('Use {device_id} as placeholder'),

                                TextInput::make('protocol_config.command_topic_template')
                                    ->label('Command Topic Template')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('devices/{device_id}/commands')
                                    ->helperText('Use {device_id} as placeholder'),

                                Select::make('protocol_config.qos')
                                    ->label('QoS Level')
                                    ->options([
                                        0 => 'At most once (0)',
                                        1 => 'At least once (1)',
                                        2 => 'Exactly once (2)',
                                    ])
                                    ->default(1)
                                    ->required(),

                                Checkbox::make('protocol_config.retain')
                                    ->label('Retain Messages')
                                    ->default(false)
                                    ->helperText('Keep the last message on the broker'),
                            ])
                            ->visible(fn (Get $get): bool => $get('default_protocol') === 'mqtt'),

                        // HTTP Configuration
                        Grid::make(2)
                            ->schema([
                                TextInput::make('protocol_config.base_url')
                                    ->label('Base URL')
                                    ->required()
                                    ->url()
                                    ->maxLength(255)
                                    ->helperText('Base URL of the HTTP API'),

                                TextInput::make('protocol_config.telemetry_endpoint')
                                    ->label('Telemetry Endpoint')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('/telemetry/{device_id}')
                                    ->helperText('Use {device_id} as placeholder'),

                                TextInput::make('protocol_config.command_endpoint')
                                    ->label('Command Endpoint')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('/commands/{device_id}')
                                    ->helperText('Use {device_id} as placeholder'),

                                Select::make('protocol_config.method')
                                    ->label('HTTP Method')
                                    ->options([
                                        'GET' => 'GET',
                                        'POST' => 'POST',
                                        'PUT' => 'PUT',
                                        'PATCH' => 'PATCH',
                                    ])
                                    ->default('POST')
                                    ->required(),

                                Select::make('protocol_config.auth_type')
                                    ->label('Authentication Type')
                                    ->options(HttpAuthType::class)
                                    ->default(HttpAuthType::None->value)
                                    ->required()
                                    ->live(),

                                TextInput::make('protocol_config.auth_username')
                                    ->label('Username')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('protocol_config.auth_type') === HttpAuthType::Basic->value),

                                TextInput::make('protocol_config.auth_password')
                                    ->label('Password')
                                    ->password()
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('protocol_config.auth_type') === HttpAuthType::Basic->value),

                                TextInput::make('protocol_config.auth_token')
                                    ->label('Bearer Token')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('protocol_config.auth_type') === HttpAuthType::Bearer->value),

                                TextInput::make('protocol_config.timeout')
                                    ->label('Timeout (seconds)')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(300)
                                    ->default(30),

                                KeyValue::make('protocol_config.headers')
                                    ->label('Custom Headers')
                                    ->keyLabel('Header Name')
                                    ->valueLabel('Header Value')
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (Get $get): bool => $get('default_protocol') === 'http'),
                    ])
                    ->columnSpanFull()
                    ->description('Configure protocol-specific connection settings'),
            ]);
    }
}
