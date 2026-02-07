<?php

declare(strict_types=1);

namespace App\Filament\Portal\Resources\DeviceManagement\Devices;

use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Filament\Admin\Resources\DeviceManagement\Devices\RelationManagers\TelemetryLogsRelationManager;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $tenantOwnershipRelationshipName = 'organization';

    public static function getNavigationGroup(): ?string
    {
        return __('IoT Management');
    }

    public static function getNavigationLabel(): string
    {
        return __('Devices');
    }

    public static function form(Schema $schema): Schema
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
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Configuration')
                    ->schema([
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
                            ->disabled(fn (Get $get) => ! $get('device_type_id')),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true),

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

    public static function infolist(Schema $schema): Schema
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
                            ->label('External ID'),

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

                        TextEntry::make('connection_state')
                            ->label('Connection State')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'online' => Color::Green,
                                'offline' => Color::Red,
                                default => Color::Gray,
                            }),

                        TextEntry::make('last_seen_at')
                            ->label('Last Seen')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make('Metadata')
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->columnSpanFull(),
                    ]),

                Section::make('Command Payload Samples')
                    ->description('Example JSON payloads this device expects on command (subscribe) topics, using schema defaults.')
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('deviceType.name')
                    ->label('Type')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

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
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('deviceType')
                    ->relationship('deviceType', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            TelemetryLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'view' => Pages\ViewDevice::route('/{record}'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
