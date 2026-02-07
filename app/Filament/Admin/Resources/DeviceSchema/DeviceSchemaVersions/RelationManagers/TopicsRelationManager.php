<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\RelationManagers;

use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
                ->required(),

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

                TextColumn::make('suffix')
                    ->copyable(),

                TextColumn::make('qos')
                    ->label('QoS'),

                IconColumn::make('retain')
                    ->boolean(),

                TextColumn::make('parameters_count')
                    ->label('Parameters')
                    ->counts('parameters'),

                TextColumn::make('sequence')
                    ->sortable(),
            ])
            ->defaultSort('sequence')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
