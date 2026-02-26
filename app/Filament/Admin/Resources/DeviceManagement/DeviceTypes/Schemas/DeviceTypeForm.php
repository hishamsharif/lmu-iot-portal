<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\Schemas;

use App\Domain\DeviceManagement\Enums\HttpAuthType;
use App\Domain\DeviceManagement\Enums\MqttSecurityMode;
use App\Domain\DeviceManagement\Enums\ProtocolType;
use App\Domain\DeviceManagement\Models\DeviceType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeviceTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Catalog Scope')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Global catalog')
                            ->helperText('Leave empty to make this type available to all organizations.')
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable name for this device type')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $currentKey = $get('key');

                                if (is_string($currentKey) && trim($currentKey) !== '') {
                                    return;
                                }

                                if (! is_string($state) || trim($state) === '') {
                                    return;
                                }

                                $slug = Str::of($state)
                                    ->lower()
                                    ->replaceMatches('/[^a-z0-9]+/', '_')
                                    ->trim('_')
                                    ->toString();

                                if ($slug !== '') {
                                    $set('key', $slug);
                                }
                            }),

                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->regex('/^[a-z0-9_]+$/')
                            ->rule(function (Get $get, ?Model $record): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                    if (! is_string($value) || trim($value) === '') {
                                        return;
                                    }

                                    $query = DeviceType::query()
                                        ->where('key', $value);

                                    if ($record !== null) {
                                        $query->whereKeyNot($record->getKey());
                                    }

                                    $organizationId = $get('organization_id');

                                    if (is_numeric($organizationId)) {
                                        $query->where('organization_id', (int) $organizationId);
                                    } else {
                                        $query->whereNull('organization_id');
                                    }

                                    if ($query->exists()) {
                                        $fail('The key has already been taken in this catalog scope.');
                                    }
                                };
                            })
                            ->helperText('Unique identifier (lowercase letters, numbers, and underscores only)'),

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
                        Grid::make(2)
                            ->schema([
                                TextInput::make('protocol_config.broker_host')
                                    ->label('Broker Host')
                                    ->required()
                                    ->placeholder('mqtt.example.com')
                                    ->maxLength(255),

                                TextInput::make('protocol_config.broker_port')
                                    ->label('Broker Port')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(65535)
                                    ->default(1883),

                                Select::make('protocol_config.security_mode')
                                    ->label('Security Mode')
                                    ->options(MqttSecurityMode::class)
                                    ->default(MqttSecurityMode::UsernamePassword->value)
                                    ->required()
                                    ->live(),

                                TextInput::make('protocol_config.username')
                                    ->label('Username')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('protocol_config.security_mode') === MqttSecurityMode::UsernamePassword->value),

                                TextInput::make('protocol_config.password')
                                    ->label('Password')
                                    ->password()
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('protocol_config.security_mode') === MqttSecurityMode::UsernamePassword->value),

                                Placeholder::make('security_mode_hint')
                                    ->hiddenLabel()
                                    ->content('X.509 mTLS mode uses per-device certificates and keys provisioned from the Device page.')
                                    ->visible(fn (Get $get): bool => $get('protocol_config.security_mode') === MqttSecurityMode::X509Mtls->value),

                                Checkbox::make('protocol_config.use_tls')
                                    ->label('Use TLS/SSL')
                                    ->default(false),

                                TextInput::make('protocol_config.base_topic')
                                    ->label('Base Topic')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('device')
                                    ->helperText('Base topic prefix. Full topic: {base_topic}/{device_uuid}/{suffix}'),
                            ])
                            ->visible(function (Get $get): bool {
                                $protocol = $get('default_protocol');

                                if ($protocol instanceof ProtocolType) {
                                    return $protocol === ProtocolType::Mqtt;
                                }

                                return $protocol === ProtocolType::Mqtt->value;
                            }),

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
                                    ->default('/telemetry')
                                    ->helperText('Endpoint path for telemetry data'),

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
                            ->visible(function (Get $get): bool {
                                $protocol = $get('default_protocol');

                                if ($protocol instanceof ProtocolType) {
                                    return $protocol === ProtocolType::Http;
                                }

                                return $protocol === ProtocolType::Http->value;
                            }),
                    ])
                    ->columnSpanFull()
                    ->description('Configure protocol-specific connection settings'),

                Section::make('Onboarding Flow')
                    ->schema([
                        Placeholder::make('onboarding_step_1')
                            ->hiddenLabel()
                            ->content('1. Save this device type with protocol defaults.'),
                        Placeholder::make('onboarding_step_2')
                            ->hiddenLabel()
                            ->content('2. Add a schema contract + active version from the "Device Schemas" relation.'),
                        Placeholder::make('onboarding_step_3')
                            ->hiddenLabel()
                            ->content('3. Create devices using this type, then open each device control dashboard to validate commands and state feedback.'),
                    ])
                    ->columnSpanFull()
                    ->columns(1),
            ]);
    }
}
