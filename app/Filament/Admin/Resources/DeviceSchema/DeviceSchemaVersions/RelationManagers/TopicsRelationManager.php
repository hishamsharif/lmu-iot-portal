<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\RelationManagers;

use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Enums\TopicLinkType;
use App\Domain\DeviceSchema\Enums\TopicPurpose;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Unique;

class TopicsRelationManager extends RelationManager
{
    protected static string $relationship = 'topics';

    public function getOwnerRecord(): DeviceSchemaVersion
    {
        /** @var DeviceSchemaVersion $ownerRecord */
        $ownerRecord = $this->ownerRecord;

        return $ownerRecord;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(100)
                ->regex('/^[a-z0-9_]+$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule): Unique {
                    /** @var int|string $ownerKey */
                    $ownerKey = $this->getOwnerRecord()->getKey();

                    return $rule->where('device_schema_version_id', $ownerKey);
                })
                ->helperText('Unique identifier (lowercase, underscores)'),

            TextInput::make('label')
                ->required()
                ->maxLength(255),

            Select::make('direction')
                ->options(TopicDirection::class)
                ->live()
                ->afterStateUpdated(function (TopicDirection|string|null $state, Set $set): void {
                    $direction = $state instanceof TopicDirection
                        ? $state
                        : TopicDirection::tryFrom((string) $state);

                    if ($direction === TopicDirection::Subscribe) {
                        $set('purpose', TopicPurpose::Command->value);
                    } elseif ($direction === TopicDirection::Publish) {
                        $set('purpose', TopicPurpose::Telemetry->value);
                    }
                })
                ->required(),

            Select::make('purpose')
                ->options(TopicPurpose::class)
                ->default(TopicPurpose::Telemetry->value),

            TextInput::make('suffix')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule): Unique {
                    /** @var int|string $ownerKey */
                    $ownerKey = $this->getOwnerRecord()->getKey();

                    return $rule->where('device_schema_version_id', $ownerKey);
                })
                ->helperText('Topic suffix appended to base_topic/{device_uuid}/{suffix}'),

            Select::make('qos')
                ->label('QoS Level')
                ->options([
                    0 => 'At most once (0)',
                    1 => 'At least once (1)',
                    2 => 'Exactly once (2)',
                ])
                ->default(1)
                ->required(),

            Checkbox::make('retain')
                ->label('Retain Messages')
                ->default(false),

            TextInput::make('sequence')
                ->integer()
                ->minValue(0)
                ->default(0),

            Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),

            Select::make('state_feedback_topic_ids')
                ->label('State Feedback Topics')
                ->multiple()
                ->searchable()
                ->options(fn (Get $get): array => $this->feedbackTopicOptions($get))
                ->helperText('Publish topics that confirm command state changes.')
                ->visible(fn (Get $get): bool => $this->isCommandTopic($get))
                ->columnSpanFull(),

            Select::make('ack_feedback_topic_ids')
                ->label('Ack Feedback Topics')
                ->multiple()
                ->searchable()
                ->options(fn (Get $get): array => $this->feedbackTopicOptions($get))
                ->helperText('Publish topics that provide command acknowledgements.')
                ->visible(fn (Get $get): bool => $this->isCommandTopic($get))
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('key')
                    ->searchable(),

                TextColumn::make('label')
                    ->searchable(),

                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (TopicDirection $state): array => match ($state) {
                        TopicDirection::Publish => Color::Blue,
                        TopicDirection::Subscribe => Color::Orange,
                    }),

                TextColumn::make('purpose')
                    ->badge()
                    ->formatStateUsing(fn (TopicPurpose|string|null $state): string => $state instanceof TopicPurpose
                        ? $state->label()
                        : (is_string($state) ? (TopicPurpose::tryFrom($state)?->label() ?? $state) : '—'))
                    ->color(fn (TopicPurpose|string|null $state): array => match ($state instanceof TopicPurpose ? $state : TopicPurpose::tryFrom((string) $state)) {
                        TopicPurpose::Command => Color::Orange,
                        TopicPurpose::State => Color::Green,
                        TopicPurpose::Telemetry => Color::Blue,
                        TopicPurpose::Ack => Color::Amber,
                        TopicPurpose::Event => Color::Gray,
                        default => Color::Gray,
                    }),

                TextColumn::make('suffix')
                    ->copyable(),

                TextColumn::make('qos')
                    ->label('QoS'),

                IconColumn::make('retain')
                    ->boolean(),

                TextColumn::make('parameters_count')
                    ->label('Parameters')
                    ->counts('parameters'),

                TextColumn::make('outgoing_links_count')
                    ->label('Feedback Links')
                    ->counts('outgoingLinks'),

                TextColumn::make('sequence')
                    ->sortable(),
            ])
            ->defaultSort('sequence')
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): SchemaVersionTopic {
                        $topic = SchemaVersionTopic::create(array_merge(
                            Arr::except($data, ['state_feedback_topic_ids', 'ack_feedback_topic_ids']),
                            ['device_schema_version_id' => $this->getOwnerRecord()->id],
                        ));

                        $this->syncFeedbackLinks($topic, $data);

                        return $topic;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, SchemaVersionTopic $record): array {
                        $record->loadMissing('outgoingLinks');

                        $data['state_feedback_topic_ids'] = $record->outgoingLinks
                            ->where('link_type', TopicLinkType::StateFeedback)
                            ->pluck('to_schema_version_topic_id')
                            ->filter(fn (mixed $id): bool => is_numeric($id))
                            ->map(fn (mixed $id): string => (string) (int) $id)
                            ->values()
                            ->all();

                        $data['ack_feedback_topic_ids'] = $record->outgoingLinks
                            ->where('link_type', TopicLinkType::AckFeedback)
                            ->pluck('to_schema_version_topic_id')
                            ->filter(fn (mixed $id): bool => is_numeric($id))
                            ->map(fn (mixed $id): string => (string) (int) $id)
                            ->values()
                            ->all();

                        return $data;
                    })
                    ->using(function (SchemaVersionTopic $record, array $data): SchemaVersionTopic {
                        $record->update(Arr::except($data, ['state_feedback_topic_ids', 'ack_feedback_topic_ids']));
                        $this->syncFeedbackLinks($record, $data);

                        return $record;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private function feedbackTopicOptions(Get $get): array
    {
        $currentRecordId = $get('id');

        return $this->getOwnerRecord()
            ->topics()
            ->where('direction', TopicDirection::Publish)
            ->when($currentRecordId, fn ($query) => $query->where('id', '!=', $currentRecordId))
            ->orderBy('sequence')
            ->get(['id', 'label', 'suffix', 'purpose'])
            ->mapWithKeys(function (SchemaVersionTopic $topic): array {
                $purpose = $topic->resolvedPurpose()->label();

                return [
                    (string) $topic->id => "{$topic->label} ({$topic->suffix}) [{$purpose}]",
                ];
            })
            ->all();
    }

    private function isCommandTopic(Get $get): bool
    {
        $purpose = $get('purpose');
        $direction = $get('direction');

        $resolvedPurpose = $purpose instanceof TopicPurpose
            ? $purpose
            : (is_string($purpose) ? TopicPurpose::tryFrom($purpose) : null);
        $resolvedDirection = $direction instanceof TopicDirection
            ? $direction
            : (is_string($direction) ? TopicDirection::tryFrom($direction) : null);

        return $resolvedPurpose === TopicPurpose::Command || $resolvedDirection === TopicDirection::Subscribe;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncFeedbackLinks(SchemaVersionTopic $topic, array $data): void
    {
        $stateTopicIds = $this->normalizeTopicIds($data['state_feedback_topic_ids'] ?? null, (int) $topic->id);
        $ackTopicIds = $this->normalizeTopicIds($data['ack_feedback_topic_ids'] ?? null, (int) $topic->id);

        $rawAllowedTopicIds = $this->getOwnerRecord()
            ->topics()
            ->where('direction', TopicDirection::Publish)
            ->pluck('id')
            ->all();

        $allowedTopicIds = [];

        foreach ($rawAllowedTopicIds as $rawAllowedTopicId) {
            if (! is_numeric($rawAllowedTopicId)) {
                continue;
            }

            $allowedTopicIds[] = (int) $rawAllowedTopicId;
        }

        $allowedMap = array_flip($allowedTopicIds);

        $normalizedState = array_values(array_filter(
            $stateTopicIds,
            fn (int $id): bool => array_key_exists($id, $allowedMap),
        ));

        $normalizedAck = array_values(array_filter(
            $ackTopicIds,
            fn (int $id): bool => array_key_exists($id, $allowedMap),
        ));

        $topic->outgoingLinks()->delete();

        foreach ($normalizedState as $toTopicId) {
            $topic->outgoingLinks()->create([
                'to_schema_version_topic_id' => $toTopicId,
                'link_type' => TopicLinkType::StateFeedback,
            ]);
        }

        foreach ($normalizedAck as $toTopicId) {
            $topic->outgoingLinks()->create([
                'to_schema_version_topic_id' => $toTopicId,
                'link_type' => TopicLinkType::AckFeedback,
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function normalizeTopicIds(mixed $ids, int $currentTopicId): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $normalized = [];

        foreach ($ids as $id) {
            if (! is_numeric($id)) {
                continue;
            }

            $normalizedId = (int) $id;

            if ($normalizedId === $currentTopicId) {
                continue;
            }

            $normalized[] = $normalizedId;
        }

        return array_values(array_unique($normalized));
    }
}
