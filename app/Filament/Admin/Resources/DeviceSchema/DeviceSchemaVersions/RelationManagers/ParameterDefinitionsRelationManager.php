<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceSchema\DeviceSchemaVersions\RelationManagers;

use App\Domain\DeviceSchema\Enums\ControlWidgetType;
use App\Domain\DeviceSchema\Enums\ParameterDataType;
use App\Domain\DeviceSchema\Enums\TopicDirection;
use App\Domain\DeviceSchema\Models\DeviceSchemaVersion;
use App\Domain\DeviceSchema\Models\ParameterDefinition;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class ParameterDefinitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'parameters';

    public function getOwnerRecord(): DeviceSchemaVersion
    {
        /** @var DeviceSchemaVersion $ownerRecord */
        $ownerRecord = $this->ownerRecord;

        return $ownerRecord;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('schema_version_topic_id')
                    ->label('Topic')
                    ->options(fn (): array => $this->getOwnerRecord()
                        ->topics()
                        ->orderBy('sequence')
                        ->get(['id', 'label', 'direction'])
                        ->mapWithKeys(fn (SchemaVersionTopic $topic): array => [
                            $topic->id => sprintf('%s (%s)', $topic->label, $topic->direction->value),
                        ])
                        ->all())
                    ->required()
                    ->live()
                    ->helperText('Select the topic this parameter belongs to'),

                TextInput::make('key')
                    ->required()
                    ->maxLength(100)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, callable $get): Unique => $rule->where('schema_version_topic_id', $get('schema_version_topic_id')),
                    ),

                TextInput::make('label')
                    ->required()
                    ->maxLength(255),

                TextInput::make('json_path')
                    ->required()
                    ->maxLength(255)
                    ->helperText(fn (Get $get): string => $this->isSubscribeTopic($get)
                        ? 'Path to place value in the command JSON (e.g. fan_speed or status.fan_speed)'
                        : 'Path to extract value from payload (e.g. $.status.temp or temp)'),

                Select::make('type')
                    ->options(ParameterDataType::class)
                    ->required(),

                TextInput::make('unit')
                    ->maxLength(50)
                    ->placeholder('Celsius'),

                CodeEditor::make('default_value')
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->helperText('Default value for this parameter in the command payload (e.g. 0, false, "auto")')
                    ->visible(fn (Get $get): bool => $this->isSubscribeTopic($get))
                    ->formatStateUsing(function (mixed $state): ?string {
                        if ($state === null) {
                            return null;
                        }

                        $encoded = json_encode($state, JSON_PRETTY_PRINT);

                        return $encoded === false ? null : $encoded;
                    })
                    ->dehydrateStateUsing(fn (?string $state): mixed => $state !== null && $state !== '' ? json_decode($state, true) : null),

                Toggle::make('required')
                    ->label('Required'),

                Toggle::make('is_critical')
                    ->label('Critical')
                    ->visible(fn (Get $get): bool => ! $this->isSubscribeTopic($get)),

                CodeEditor::make('validation_rules')
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->helperText(fn (Get $get): string => $this->isSubscribeTopic($get)
                        ? 'JSON rules for command validation and UI hints (e.g. {"min": 0, "max": 100} for sliders, {"enum": ["cooling", "heating", "auto"]} for dropdowns)'
                        : 'JSON rules, e.g. {"min": -40, "max": 85}')
                    ->formatStateUsing(function (mixed $state): ?string {
                        if (is_array($state)) {
                            $encoded = json_encode($state, JSON_PRETTY_PRINT);

                            return $encoded === false ? null : $encoded;
                        }

                        return is_string($state) ? $state : null;
                    })
                    ->dehydrateStateUsing(fn (?string $state): mixed => $state ? json_decode($state, true) : null),

                Select::make('control_ui.widget')
                    ->label('Control Widget')
                    ->options(ControlWidgetType::class)
                    ->placeholder('Auto infer from type and validation rules')
                    ->live()
                    ->helperText('Override inferred widget type when this command parameter needs special UI treatment.')
                    ->visible(fn (Get $get): bool => $this->isSubscribeTopic($get))
                    ->columnSpanFull(),

                TextInput::make('control_ui.min')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $this->isNumericWidgetSelected($get)),

                TextInput::make('control_ui.max')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $this->isNumericWidgetSelected($get)),

                TextInput::make('control_ui.step')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $this->isNumericWidgetSelected($get)),

                TextInput::make('control_ui.button_value')
                    ->label('Button Value')
                    ->helperText('Value injected when the button is pressed (e.g. true, 1, "pressed").')
                    ->visible(fn (Get $get): bool => $this->selectedWidget($get) === ControlWidgetType::Button),

                Select::make('control_ui.color_format')
                    ->label('Color Format')
                    ->options([
                        'hex' => 'Hex (#RRGGBB)',
                        'rgb' => 'RGB string (rgb(255,0,0))',
                    ])
                    ->default('hex')
                    ->visible(fn (Get $get): bool => $this->selectedWidget($get) === ControlWidgetType::Color),

                TagsInput::make('validation_error_code')
                    ->label('Validation error codes')
                    ->separator(',')
                    ->placeholder('TEMP_RANGE')
                    ->helperText('Add one or more codes. Stored as a comma-separated string (e.g. TEMP_RANGE, OUT_OF_RANGE).')
                    ->visible(fn (Get $get): bool => ! $this->isSubscribeTopic($get)),

                CodeEditor::make('mutation_expression')
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->helperText('JsonLogic expression')
                    ->visible(fn (Get $get): bool => ! $this->isSubscribeTopic($get))
                    ->formatStateUsing(function (mixed $state): ?string {
                        if (is_array($state)) {
                            $encoded = json_encode($state, JSON_PRETTY_PRINT);

                            return $encoded === false ? null : $encoded;
                        }

                        return is_string($state) ? $state : null;
                    })
                    ->dehydrateStateUsing(fn (?string $state): mixed => $state ? json_decode($state, true) : null),

                TextInput::make('sequence')
                    ->integer()
                    ->minValue(0)
                    ->default(0),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->columns(2);
    }

    /**
     * Determine if the currently selected topic is a subscribe topic.
     */
    private function isSubscribeTopic(Get $get): bool
    {
        $topicId = $get('schema_version_topic_id');

        if (! $topicId) {
            return false;
        }

        return SchemaVersionTopic::query()
            ->where('id', $topicId)
            ->where('direction', TopicDirection::Subscribe)
            ->exists();
    }

    private function isNumericWidgetSelected(Get $get): bool
    {
        $widget = $this->selectedWidget($get);

        return in_array($widget, [
            ControlWidgetType::Slider,
            ControlWidgetType::Number,
        ], true);
    }

    private function selectedWidget(Get $get): ?ControlWidgetType
    {
        $widget = $get('control_ui.widget');

        if ($widget instanceof ControlWidgetType) {
            return $widget;
        }

        if (is_string($widget)) {
            return ControlWidgetType::tryFrom($widget);
        }

        return null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                TextColumn::make('topic.label')
                    ->label('Topic')
                    ->searchable(),

                TextColumn::make('key')
                    ->searchable(),

                TextColumn::make('label')
                    ->searchable(),

                TextColumn::make('type')
                    ->formatStateUsing(fn (ParameterDataType|string $state): string => $state instanceof ParameterDataType ? $state->label() : (string) $state)
                    ->badge(),

                IconColumn::make('required')
                    ->boolean(),

                IconColumn::make('is_critical')
                    ->label('Critical')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('sequence')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): ParameterDefinition {
                        return ParameterDefinition::create($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
