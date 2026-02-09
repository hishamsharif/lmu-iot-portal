<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DeviceManagement\Devices\Pages;

use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Models\DeviceCommandLog;
use App\Domain\DeviceControl\Services\CommandPayloadResolver;
use App\Domain\DeviceControl\Services\ControlSchemaBuilder;
use App\Domain\DeviceControl\Services\DeviceCommandDispatcher;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceManagement\Publishing\Nats\NatsDeviceStateStore;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use App\Filament\Actions\DeviceManagement\ViewFirmwareAction;
use App\Filament\Admin\Resources\DeviceManagement\Devices\DeviceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Radio;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class DeviceControlDashboard extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.admin.resources.device-management.devices.pages.device-control-dashboard';

    public ?string $selectedTopicId = null;

    public ?string $commandPayloadJson = '{}';

    public bool $useAdvancedJson = false;

    /**
     * @var array<string, mixed>
     */
    public array $controlValues = [];

    /**
     * @var array<int, array{
     *     key: string,
     *     label: string,
     *     json_path: string,
     *     widget: string,
     *     type: string,
     *     required: bool,
     *     default: mixed,
     *     min: int|float|null,
     *     max: int|float|null,
     *     step: int|float,
     *     options: array<int|string, string>,
     *     unit: string|null,
     *     button_value: mixed
     * }>
     */
    public array $controlSchema = [];

    /**
     * Initial device state loaded from the NATS KV store on page mount.
     *
     * @var array{topic: string, payload: array<string, mixed>, stored_at: string}|null
     */
    public ?array $initialDeviceState = null;

    /**
     * Initial per-topic device states loaded from the NATS KV store on page mount.
     *
     * @var array<int, array{topic: string, payload: array<string, mixed>, stored_at: string}>
     */
    public array $initialDeviceStates = [];

    public static function getNavigationLabel(): string
    {
        return 'Control Dashboard';
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('viewDevice')
                ->label('View Device')
                ->url(fn (): string => DeviceResource::getUrl('view', ['record' => $this->getRecord()])),
            ViewFirmwareAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Group::make([
                        Radio::make('selectedTopicId')
                            ->label('Subscribe Topic')
                            ->options(fn (): array => $this->getSubscribeTopicOptionsProperty())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $this->loadDefaultPayload();
                                $set('commandPayloadJson', $this->commandPayloadJson);
                            }),

                        TextEntry::make('mqttTopic')
                            ->label('MQTT Topic')
                            ->state(fn (): string => $this->getResolvedMqttTopic())
                            ->copyable()
                            ->fontFamily('mono'),

                        TextEntry::make('natsSubject')
                            ->label('NATS Subject')
                            ->state(fn (): string => $this->getResolvedNatsSubject())
                            ->copyable()
                            ->fontFamily('mono'),

                        TextEntry::make('mqttSettings')
                            ->label('QoS / Retain')
                            ->state(fn (): string => $this->getMqttSettings()),
                    ]),

                    Group::make([
                        Checkbox::make('useAdvancedJson')
                            ->label('Advanced JSON mode')
                            ->helperText('Enable to edit and send raw JSON payloads directly.')
                            ->live(),

                        CodeEditor::make('commandPayloadJson')
                            ->hiddenLabel()
                            ->language(Language::Json)
                            ->wrap()
                            ->required()
                            ->markAsRequired(false)
                            ->visible(fn (): bool => $this->useAdvancedJson),
                    ]),
                ]),
            ]);
    }

    public function getTitle(): string
    {
        /** @var Device $device */
        $device = $this->getRecord();

        return "Control Dashboard — {$device->name}";
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->getRecord()->loadMissing('deviceType', 'schemaVersion.topics.parameters');

        $this->loadDefaultPayload();
        $this->loadInitialDeviceState();
    }

    public function loadDefaultPayload(): void
    {
        $topics = $this->getSubscribeTopics();

        if ($topics->isEmpty()) {
            $this->commandPayloadJson = '{}';
            $this->controlSchema = [];
            $this->controlValues = [];

            return;
        }

        $topic = $this->selectedTopicId
            ? $topics->firstWhere('id', (int) $this->selectedTopicId)
            : $topics->first();

        if ($topic) {
            $this->selectedTopicId = (string) $topic->id;
            $template = $topic->buildCommandPayloadTemplate();
            $this->commandPayloadJson = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

            /** @var ControlSchemaBuilder $builder */
            $builder = app(ControlSchemaBuilder::class);
            $this->controlSchema = $builder->buildForTopic($topic);
            $this->controlValues = $builder->defaultControlValues($topic);
            $this->normalizeControlValuesForUi();
        }
    }

    public function sendButtonCommand(string $parameterKey): void
    {
        if ($parameterKey === '') {
            return;
        }

        $buttonControl = null;

        foreach ($this->controlSchema as $control) {
            if ($control['key'] !== $parameterKey) {
                continue;
            }

            $buttonControl = $control;
            break;
        }

        if ($buttonControl === null || $buttonControl['widget'] !== 'button') {
            return;
        }

        $this->controlValues[$parameterKey] = $buttonControl['button_value'] ?? true;
        $this->useAdvancedJson = false;

        $this->sendCommand();
    }

    public function sendCommand(): void
    {
        if (! $this->selectedTopicId) {
            Notification::make()
                ->title('No topic selected')
                ->body('Please select a subscribe topic first.')
                ->warning()
                ->send();

            return;
        }

        $topic = SchemaVersionTopic::find((int) $this->selectedTopicId);

        if (! $topic) {
            Notification::make()
                ->title('Topic not found')
                ->danger()
                ->send();

            return;
        }

        /** @var CommandPayloadResolver $payloadResolver */
        $payloadResolver = app(CommandPayloadResolver::class);

        $payload = [];
        $errors = [];

        if ($this->useAdvancedJson) {
            /** @var mixed $decodedPayload */
            $decodedPayload = json_decode($this->commandPayloadJson ?? '{}', true);

            if (! is_array($decodedPayload)) {
                Notification::make()
                    ->title('Invalid JSON')
                    ->body('The command payload is not valid JSON.')
                    ->danger()
                    ->send();

                return;
            }

            $payload = $this->normalizePayloadArray($decodedPayload);
            $errors = $payloadResolver->validatePayload($topic, $payload);
        } else {
            $resolved = $payloadResolver->resolveFromControls($topic, $this->controlValues);
            $payload = $resolved['payload'];
            $errors = $resolved['errors'];
        }

        if ($errors !== []) {
            Notification::make()
                ->title('Invalid control values')
                ->body($this->formatValidationErrors($errors))
                ->danger()
                ->send();

            return;
        }

        $this->commandPayloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

        /** @var Device $device */
        $device = $this->getRecord();

        /** @var DeviceCommandDispatcher $dispatcher */
        $dispatcher = app(DeviceCommandDispatcher::class);

        $commandLog = $dispatcher->dispatch(
            device: $device,
            topic: $topic,
            payload: $payload,
            userId: is_int(auth()->id()) ? auth()->id() : null,
        );

        if ($commandLog->status === CommandStatus::Failed) { /** @phpstan-ignore identical.alwaysFalse */
            Notification::make()
                ->title('Command failed')
                ->body($commandLog->error_message ?? 'Failed to publish command.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Command sent')
            ->body("Published to {$topic->suffix}")
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        /** @var Device $device */
        $device = $this->getRecord();

        return $table
            ->query(
                DeviceCommandLog::query()
                    ->where('device_id', $device->id)
                    ->latest()
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('topic.suffix')
                    ->label('Topic'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CommandStatus $state): string => match ($state) {
                        CommandStatus::Pending => 'gray',
                        CommandStatus::Sent => 'info',
                        CommandStatus::Acknowledged => 'warning',
                        CommandStatus::Completed => 'success',
                        CommandStatus::Failed => 'danger',
                        CommandStatus::Timeout => 'warning',
                    }),

                TextColumn::make('command_payload')
                    ->label('Payload')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? (string) json_encode($state) : (string) $state)
                    ->limit(60),

                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->poll('5s');
    }

    private function getSelectedTopic(): ?SchemaVersionTopic
    {
        if (! $this->selectedTopicId) {
            return null;
        }

        return $this->getSubscribeTopics()->firstWhere('id', (int) $this->selectedTopicId);
    }

    public function getResolvedMqttTopic(): string
    {
        $topic = $this->getSelectedTopic();

        if (! $topic) {
            return '—';
        }

        /** @var Device $device */
        $device = $this->getRecord();

        return $topic->resolvedTopic($device);
    }

    public function getResolvedNatsSubject(): string
    {
        return str_replace('/', '.', $this->getResolvedMqttTopic());
    }

    public function getMqttSettings(): string
    {
        $topic = $this->getSelectedTopic();

        if (! $topic) {
            return '—';
        }

        $qos = $topic->qos ?? 0;
        $retain = ($topic->retain ?? false) ? 'Yes' : 'No';

        return "QoS {$qos} · Retain: {$retain}";
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchemaVersionTopic>
     */
    private function getSubscribeTopics(): \Illuminate\Support\Collection
    {
        /** @var Device $device */
        $device = $this->getRecord();

        $device->loadMissing('schemaVersion.topics.parameters');

        return $device->schemaVersion?->topics
            ?->filter(fn (SchemaVersionTopic $t): bool => $t->isSubscribe())
            ->sortBy('sequence')
                ?? collect();
    }

    /**
     * @return array<int|string, string>
     */
    public function getSubscribeTopicOptionsProperty(): array
    {
        $options = [];

        foreach ($this->getSubscribeTopics() as $topic) {
            $options[(string) $topic->id] = "{$topic->label} ({$topic->suffix})";
        }

        return $options;
    }

    public function getDeviceUuidProperty(): string
    {
        /** @var Device $device */
        $device = $this->getRecord();

        return $device->uuid;
    }

    private function loadInitialDeviceState(): void
    {
        /** @var Device $device */
        $device = $this->getRecord();

        try {
            /** @var NatsDeviceStateStore $stateStore */
            $stateStore = app(NatsDeviceStateStore::class);
            $this->initialDeviceStates = $stateStore->getAllStates($device->uuid);
            $this->initialDeviceState = $this->initialDeviceStates[0] ?? null;
        } catch (\Throwable) {
            $this->initialDeviceStates = [];
            $this->initialDeviceState = null;
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function formatValidationErrors(array $errors): string
    {
        $messages = [];

        foreach ($errors as $key => $message) {
            $messages[] = "{$key}: {$message}";
        }

        return implode("\n", $messages);
    }

    private function normalizeControlValuesForUi(): void
    {
        foreach ($this->controlSchema as $control) {
            if ($control['widget'] !== 'json') {
                continue;
            }

            $key = $control['key'];

            if (! array_key_exists($key, $this->controlValues)) {
                continue;
            }

            $current = $this->controlValues[$key];

            if (is_array($current)) {
                $this->controlValues[$key] = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayloadArray(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = is_string($key) ? $key : (string) $key;
            $normalized[$normalizedKey] = is_array($value)
                ? $this->normalizePayloadArray($value)
                : $value;
        }

        return $normalized;
    }
}
