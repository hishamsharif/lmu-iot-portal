<?php

declare(strict_types=1);

namespace App\Filament\Actions\DeviceManagement;

use App\Domain\DeviceControl\Enums\CommandStatus;
use App\Domain\DeviceControl\Services\DeviceCommandDispatcher;
use App\Domain\DeviceManagement\Models\Device;
use App\Domain\DeviceSchema\Models\SchemaVersionTopic;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

final class SendCommandActions
{
    public static function recordAction(): Action
    {
        return Action::make('sendCommand')
            ->label('Send Command')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading('Send Command to Device')
            ->modalDescription('Select a subscribe topic and send a JSON command payload to the device via NATS.')
            ->tooltip(fn (Device $record): ?string => self::missingSubscribeTopicReason($record))
            ->schema([
                Radio::make('schema_version_topic_id')
                    ->label('Subscribe Topic')
                    ->helperText('Choose which command topic to publish to.')
                    ->options(fn (Device $record): array => self::subscribeTopicOptions($record))
                    ->required()
                    ->live()
                    ->columns(1),

                Textarea::make('command_payload_json')
                    ->label('Command Payload (JSON)')
                    ->helperText('Edit the JSON payload before sending. The template is pre-filled from the schema defaults.')
                    ->rows(12)
                    ->extraAttributes(['class' => 'font-mono text-sm'])
                    ->default(fn (Device $record): string => self::defaultPayloadJson($record))
                    ->required(),
            ])
            ->disabled(fn (Device $record): bool => self::missingSubscribeTopicReason($record) !== null)
            ->action(function (array $data, Device $record): void {
                $topicId = isset($data['schema_version_topic_id']) ? (int) $data['schema_version_topic_id'] : null;
                $payloadJson = $data['command_payload_json'] ?? '{}';

                $topic = SchemaVersionTopic::find($topicId);

                if (! $topic) {
                    Notification::make()
                        ->title('Topic not found')
                        ->body('The selected subscribe topic could not be found.')
                        ->danger()
                        ->send();

                    return;
                }

                /** @var array<string, mixed>|null $decodedPayload */
                $decodedPayload = json_decode($payloadJson, true);

                if (! is_array($decodedPayload)) {
                    Notification::make()
                        ->title('Invalid JSON')
                        ->body('The command payload is not valid JSON.')
                        ->danger()
                        ->send();

                    return;
                }

                /** @var DeviceCommandDispatcher $dispatcher */
                $dispatcher = app(DeviceCommandDispatcher::class);

                $commandLog = $dispatcher->dispatch(
                    device: $record,
                    topic: $topic,
                    payload: $decodedPayload,
                    userId: is_int(auth()->id()) ? auth()->id() : null,
                );

                if ($commandLog->status === CommandStatus::Failed) { /** @phpstan-ignore identical.alwaysFalse */
                    Notification::make()
                        ->title('Command failed')
                        ->body($commandLog->error_message ?? 'Failed to publish command to NATS.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Command sent')
                    ->body("Command published to {$topic->suffix}. Check the dashboard for real-time status.")
                    ->success()
                    ->send();
            });
    }

    private static function defaultPayloadJson(Device $record): string
    {
        $topics = self::subscribeTopics($record);

        if ($topics->isEmpty()) {
            return '{}';
        }

        $firstTopic = $topics->first();
        $template = $firstTopic->buildCommandPayloadTemplate();

        return json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return Collection<int, SchemaVersionTopic>
     */
    private static function subscribeTopics(Device $record): Collection
    {
        $record->loadMissing('schemaVersion.topics.parameters');

        return $record->schemaVersion?->topics
            ?->filter(fn (SchemaVersionTopic $topic): bool => $topic->isSubscribe())
            ->sortBy('sequence')
                ?? collect();
    }

    /**
     * @return array<int|string, string>
     */
    private static function subscribeTopicOptions(Device $record): array
    {
        $topics = self::subscribeTopics($record);
        $options = [];

        foreach ($topics as $topic) {
            $options[(string) $topic->id] = "{$topic->label} ({$topic->suffix})";
        }

        return $options;
    }

    private static function missingSubscribeTopicReason(Device $record): ?string
    {
        if ($record->getAttribute('device_schema_version_id') === null) {
            return 'Assign a schema version to this device to send commands.';
        }

        if (self::subscribeTopics($record)->isEmpty()) {
            return 'No subscribe (command) topics are configured for this schema version.';
        }

        return null;
    }
}
