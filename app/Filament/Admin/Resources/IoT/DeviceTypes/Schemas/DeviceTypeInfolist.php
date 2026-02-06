<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IoT\DeviceTypes\Schemas;

use App\Domain\IoT\Enums\HttpAuthType;
use App\Domain\IoT\Enums\ProtocolType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

class DeviceTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextEntry::make('key')
                            ->icon(Heroicon::OutlinedKey)
                            ->copyable(),

                        TextEntry::make('name')
                            ->weight('medium'),

                        TextEntry::make('default_protocol')
                            ->label('Protocol')
                            ->badge()
                            ->formatStateUsing(fn (ProtocolType $state): string => $state->label())
                            ->color(fn (ProtocolType $state): array => match ($state) {
                                ProtocolType::Mqtt => Color::Blue,
                                ProtocolType::Http => Color::Green,
                            })
                            ->icon(fn (ProtocolType $state) => match ($state) {
                                ProtocolType::Mqtt => Heroicon::OutlinedSignal,
                                ProtocolType::Http => Heroicon::OutlinedGlobeAlt,
                            }),

                        TextEntry::make('organization.name')
                            ->label('Organization')
                            ->placeholder('Global Catalog')
                            ->icon(fn ($record): string => $record->organization_id
                                ? Heroicon::OutlinedBuildingOffice->value
                                : Heroicon::OutlinedGlobeAlt->value
                            ),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedClock),

                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedClock),
                    ])
                    ->columnSpan(1),

                // MQTT Protocol Configuration
                Section::make('MQTT Configuration')
                    ->schema([
                        TextEntry::make('protocol_config.broker_host')
                            ->label('Broker Host')
                            ->state(fn ($record): ?string => $record->protocol_config?->brokerHost),

                        TextEntry::make('protocol_config.broker_port')
                            ->label('Broker Port')
                            ->state(fn ($record): ?int => $record->protocol_config?->brokerPort),

                        TextEntry::make('protocol_config.username')
                            ->label('Username')
                            ->state(fn ($record): ?string => $record->protocol_config?->username)
                            ->placeholder('—'),

                        IconEntry::make('protocol_config.use_tls')
                            ->label('TLS/SSL')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedShieldCheck)
                            ->falseIcon(Heroicon::OutlinedShieldExclamation)
                            ->trueColor(Color::Green)
                            ->falseColor(Color::Gray)
                            ->state(fn ($record): ?bool => $record->protocol_config?->useTls),

                        TextEntry::make('protocol_config.telemetry_topic_template')
                            ->label('Telemetry Topic')
                            ->copyable()
                            ->state(fn ($record): ?string => $record->protocol_config?->telemetryTopicTemplate),

                        TextEntry::make('protocol_config.command_topic_template')
                            ->label('Command Topic')
                            ->copyable()
                            ->state(fn ($record): ?string => $record->protocol_config?->controlTopicTemplate),

                        TextEntry::make('protocol_config.qos')
                            ->label('QoS Level')
                            ->state(fn ($record): ?int => $record->protocol_config?->qos)
                            ->formatStateUsing(fn ($state): string => match ($state) {
                                0 => 'At most once (0)',
                                1 => 'At least once (1)',
                                2 => 'Exactly once (2)',
                                default => (string) $state,
                            }),

                        IconEntry::make('protocol_config.retain')
                            ->label('Retain Messages')
                            ->boolean()
                            ->state(fn ($record): ?bool => $record->protocol_config?->retain),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($record): bool => $record->default_protocol === ProtocolType::Mqtt),

                // HTTP Protocol Configuration
                Section::make('HTTP Configuration')
                    ->schema([
                        TextEntry::make('protocol_config.base_url')
                            ->label('Base URL')
                            ->copyable()
                            ->url(fn ($state): string => $state, shouldOpenInNewTab: true)
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->state(fn ($record): ?string => $record->protocol_config?->baseUrl),

                        TextEntry::make('protocol_config.telemetry_endpoint')
                            ->label('Telemetry Endpoint')
                            ->copyable()
                            ->state(fn ($record): ?string => $record->protocol_config?->telemetryEndpoint),

                        TextEntry::make('protocol_config.command_endpoint')
                            ->label('Command Endpoint')
                            ->copyable()
                            ->state(fn ($record): ?string => $record->protocol_config?->controlEndpoint),

                        TextEntry::make('protocol_config.method')
                            ->label('HTTP Method')
                            ->state(fn ($record): ?string => $record->protocol_config?->method)
                            ->badge()
                            ->color(fn ($state): array => match ($state) {
                                'GET' => Color::Blue,
                                'POST' => Color::Green,
                                'PUT' => Color::Amber,
                                'PATCH' => Color::Orange,
                                default => Color::Gray,
                            }),

                        TextEntry::make('protocol_config.auth_type')
                            ->label('Authentication')
                            ->state(fn ($record): ?HttpAuthType => $record->protocol_config?->authType)
                            ->formatStateUsing(fn (HttpAuthType $state): string => $state->label())
                            ->badge()
                            ->color(fn (HttpAuthType $state): array => match ($state) {
                                HttpAuthType::None => Color::Gray,
                                HttpAuthType::Basic => Color::Blue,
                                HttpAuthType::Bearer => Color::Green,
                            }),

                        TextEntry::make('protocol_config.timeout')
                            ->label('Timeout')
                            ->suffix(' seconds')
                            ->state(fn ($record): ?int => $record->protocol_config?->timeout),

                        KeyValueEntry::make('protocol_config.headers')
                            ->label('Custom Headers')
                            ->columnSpanFull()
                            ->state(fn ($record): array => $record->protocol_config?->headers ?? [])
                            ->visible(fn ($record): bool => ! empty($record->protocol_config?->headers)),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($record): bool => $record->default_protocol === ProtocolType::Http),
            ]);
    }
}
