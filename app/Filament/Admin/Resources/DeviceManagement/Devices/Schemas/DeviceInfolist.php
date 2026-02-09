<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Schemas;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Filament\Admin\Resources\DeviceManagement\DeviceTypes\DeviceTypeResource;
use App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\DeviceSchemaVersionResource;
use App\Filament\Admin\Resources\Shared\Organizations\OrganizationResource;
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
                            ->label('Organization')
                            ->url(fn (Device $record): ?string => $record->organization_id
                                ? OrganizationResource::getUrl('view', ['record' => $record->organization_id])
                                : null),

                        TextEntry::make('deviceType.name')
                            ->label('Device Type')
                            ->url(fn (Device $record): ?string => $record->device_type_id
                                ? DeviceTypeResource::getUrl('view', ['record' => $record->device_type_id])
                                : null),

                        TextEntry::make('schemaVersion.version')
                            ->label('Schema Version')
                            ->formatStateUsing(fn ($state) => "Version {$state}")
                            ->url(fn (Device $record): ?string => $record->device_schema_version_id
                                ? DeviceSchemaVersionResource::getUrl('view', ['record' => $record->device_schema_version_id])
                                : null),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Active')
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

                Section::make('Command Payload Samples')
                    ->description('These are example JSON payloads the device expects on subscribe topics, using default values from the schema.')
                    ->schema([
                        KeyValueEntry::make('command_payload_samples')
                            ->valueLabel('JSON')
                            ->columnSpanFull()
                            ->state(function (Device $record): array {
                                $record->loadMissing('schemaVersion.topics.parameters');

                                $topics = $record->schemaVersion?->topics
                                    ?->filter(fn (SchemaVersionTopic $topic): bool => $topic->isSubscribe())
                                    ->sortBy('sequence');

                                if (! $topics || $topics->isEmpty()) {
                                    return [];
                                }

                                return $topics->mapWithKeys(function (SchemaVersionTopic $topic): array {
                                    $template = $topic->buildCommandPayloadTemplate();

                                    return [
                                        $topic->key => json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
                                    ];
                                })->all();
                            })
                            ->visible(fn (Device $record): bool => $record->getAttribute('device_schema_version_id') !== null),
                    ]),

                Section::make('MQTT Publish Payload Samples')
                    ->description('Example topics + JSON payload structure the device should publish (Device → Platform). Copy and paste into your MQTT client.')
                    ->schema([
                        TextEntry::make('mqtt_publish_payload_samples')
                            ->label('Publish Samples')
                            ->copyable()
                            ->placeholder('—')
                            ->extraAttributes(['class' => 'font-mono whitespace-pre-wrap'])
                            ->state(function (Device $record): string {
                                $record->loadMissing('schemaVersion.topics.parameters', 'deviceType');

                                $topics = $record->schemaVersion?->topics
                                    ?->filter(fn (SchemaVersionTopic $topic): bool => $topic->isPublish())
                                    ->sortBy('sequence');

                                if (! $topics || $topics->isEmpty()) {
                                    return '';
                                }

                                $samples = $topics->map(function (SchemaVersionTopic $topic) use ($record): string {
                                    $resolvedTopic = $topic->resolvedTopic($record);
                                    $template = $topic->buildPublishPayloadTemplate();
                                    $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

                                    $qos = $topic->qos ?? 0;
                                    $retain = $topic->retain ? 'true' : 'false';

                                    return "Topic: {$resolvedTopic}\nQoS: {$qos}\nRetain: {$retain}\nPayload:\n{$json}";
                                })->all();

                                return implode("\n\n", $samples);
                            })
                            ->visible(fn (Device $record): bool => $record->getAttribute('device_schema_version_id') !== null),
                    ]),
            ]);
    }
}
