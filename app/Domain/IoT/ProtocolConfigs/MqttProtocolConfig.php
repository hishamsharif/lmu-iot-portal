<?php

declare(strict_types=1);

namespace App\Domain\IoT\ProtocolConfigs;

use App\Domain\IoT\Contracts\ProtocolConfigInterface;

final readonly class MqttProtocolConfig implements ProtocolConfigInterface
{
    public function __construct(
        public string $brokerHost,
        public int $brokerPort = 1883,
        public ?string $username = null,
        public ?string $password = null,
        public bool $useTls = false,
        public string $telemetryTopicTemplate = 'device/:device_uuid/data',
        public string $controlTopicTemplate = 'device/:device_uuid/ctrl',
        public int $qos = 1,
        public bool $retain = false,
    ) {
    }

    public function validate(): bool
    {
        return !empty($this->brokerHost)
            && $this->brokerPort > 0
            && $this->brokerPort <= 65535
            && in_array($this->qos, [0, 1, 2], true);
    }

    public function getTelemetryTopicTemplate(): string
    {
        return $this->telemetryTopicTemplate;
    }

    public function getControlTopicTemplate(): ?string
    {
        return $this->controlTopicTemplate;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'broker_host' => $this->brokerHost,
            'broker_port' => $this->brokerPort,
            'username' => $this->username,
            'password' => $this->password,
            'use_tls' => $this->useTls,
            'telemetry_topic_template' => $this->telemetryTopicTemplate,
            'control_topic_template' => $this->controlTopicTemplate,
            'qos' => $this->qos,
            'retain' => $this->retain,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            brokerHost: $data['broker_host'] ?? throw new \InvalidArgumentException('broker_host is required'),
            brokerPort: $data['broker_port'] ?? 1883,
            username: $data['username'] ?? null,
            password: $data['password'] ?? null,
            useTls: $data['use_tls'] ?? false,
            telemetryTopicTemplate: $data['telemetry_topic_template'] ?? 'device/:device_uuid/data',
            controlTopicTemplate: $data['control_topic_template'] ?? 'device/:device_uuid/ctrl',
            qos: $data['qos'] ?? 1,
            retain: $data['retain'] ?? false,
        );
    }
}
