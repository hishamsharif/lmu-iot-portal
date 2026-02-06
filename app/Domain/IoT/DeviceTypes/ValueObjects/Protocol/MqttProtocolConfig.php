<?php

declare(strict_types=1);

namespace App\Domain\IoT\DeviceTypes\ValueObjects\Protocol;

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
        if ($this->brokerPort < 1 || $this->brokerPort > 65535) {
            throw new \InvalidArgumentException('Broker port must be between 1 and 65535');
        }

        if (! in_array($this->qos, [0, 1, 2], true)) {
            throw new \InvalidArgumentException('QoS must be 0, 1, or 2');
        }
    }

    public function getTelemetryTopicTemplate(): string
    {
        return $this->telemetryTopicTemplate;
    }

    public function getControlTopicTemplate(): string
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
        $brokerHost = $data['broker_host'] ?? null;
        if (! is_string($brokerHost) || $brokerHost === '') {
            throw new \InvalidArgumentException('broker_host is required');
        }

        $brokerPortValue = $data['broker_port'] ?? 1883;
        $brokerPort = is_numeric($brokerPortValue) ? (int) $brokerPortValue : 1883;

        $username = isset($data['username']) && is_string($data['username']) ? $data['username'] : null;
        $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : null;
        $useTls = (bool) ($data['use_tls'] ?? false);

        $telemetryTopicTemplate = $data['telemetry_topic_template'] ?? null;
        $telemetryTopicTemplate = is_string($telemetryTopicTemplate)
            ? $telemetryTopicTemplate
            : 'device/:device_uuid/data';

        $controlTopicTemplate = $data['control_topic_template'] ?? null;
        $controlTopicTemplate = is_string($controlTopicTemplate)
            ? $controlTopicTemplate
            : 'device/:device_uuid/ctrl';

        $qosValue = $data['qos'] ?? 1;
        $qos = is_numeric($qosValue) ? (int) $qosValue : 1;

        $retain = (bool) ($data['retain'] ?? false);

        return new self(
            brokerHost: $brokerHost,
            brokerPort: $brokerPort,
            username: $username,
            password: $password,
            useTls: $useTls,
            telemetryTopicTemplate: $telemetryTopicTemplate,
            controlTopicTemplate: $controlTopicTemplate,
            qos: $qos,
            retain: $retain,
        );
    }
}
