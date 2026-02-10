<?php

declare(strict_types=1);

namespace App\Domain\DataIngestion\DTO;

use Illuminate\Support\Carbon;

final readonly class IncomingTelemetryEnvelope
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $sourceSubject,
        public string $mqttTopic,
        public array $payload,
        public ?string $deviceUuid = null,
        public ?string $deviceExternalId = null,
        public ?string $messageId = null,
        public ?Carbon $receivedAt = null,
    ) {}

    public function deduplicationKey(): string
    {
        if (is_string($this->messageId) && trim($this->messageId) !== '') {
            return hash('sha256', $this->sourceSubject.'|'.trim($this->messageId));
        }

        $encodedPayload = json_encode($this->payload, JSON_THROW_ON_ERROR);
        $receivedAtKey = $this->resolveReceivedAt()->toIso8601String();

        return hash('sha256', $this->sourceSubject.'|'.$encodedPayload.'|'.$receivedAtKey);
    }

    public function resolveReceivedAt(): Carbon
    {
        return $this->receivedAt ?? now();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_subject' => $this->sourceSubject,
            'mqtt_topic' => $this->mqttTopic,
            'payload' => $this->payload,
            'device_uuid' => $this->deviceUuid,
            'device_external_id' => $this->deviceExternalId,
            'message_id' => $this->messageId,
            'received_at' => $this->resolveReceivedAt()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $receivedAt = $data['received_at'] ?? null;

        return new self(
            sourceSubject: is_string($data['source_subject'] ?? null) ? $data['source_subject'] : '',
            mqttTopic: is_string($data['mqtt_topic'] ?? null) ? $data['mqtt_topic'] : '',
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            deviceUuid: is_string($data['device_uuid'] ?? null) ? $data['device_uuid'] : null,
            deviceExternalId: is_string($data['device_external_id'] ?? null) ? $data['device_external_id'] : null,
            messageId: is_string($data['message_id'] ?? null) ? $data['message_id'] : null,
            receivedAt: is_string($receivedAt) ? Carbon::parse($receivedAt) : null,
        );
    }
}
