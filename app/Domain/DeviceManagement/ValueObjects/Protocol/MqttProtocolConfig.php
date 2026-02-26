<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\ValueObjects\Protocol;

use App\Domain\DeviceManagement\Enums\MqttSecurityMode;

final readonly class MqttProtocolConfig implements ProtocolConfigInterface
{
    public function __construct(
        public string $brokerHost,
        public int $brokerPort = 1883,
        public ?string $username = null,
        public ?string $password = null,
        public bool $useTls = false,
        public string $baseTopic = 'device',
        public MqttSecurityMode $securityMode = MqttSecurityMode::UsernamePassword,
    ) {
        if ($this->brokerPort < 1 || $this->brokerPort > 65535) {
            throw new \InvalidArgumentException('Broker port must be between 1 and 65535');
        }
    }

    public function getBaseTopic(): string
    {
        return $this->baseTopic;
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
            'base_topic' => $this->baseTopic,
            'security_mode' => $this->securityMode->value,
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

        $baseTopic = $data['base_topic'] ?? null;
        $baseTopic = is_string($baseTopic) && $baseTopic !== '' ? $baseTopic : 'device';

        $securityMode = $data['security_mode'] ?? MqttSecurityMode::UsernamePassword->value;
        $resolvedSecurityMode = $securityMode instanceof MqttSecurityMode
            ? $securityMode
            : ((is_string($securityMode) || is_int($securityMode))
                ? MqttSecurityMode::tryFrom((string) $securityMode)
                : null);

        return new self(
            brokerHost: $brokerHost,
            brokerPort: $brokerPort,
            username: $username,
            password: $password,
            useTls: $useTls,
            baseTopic: $baseTopic,
            securityMode: $resolvedSecurityMode ?? MqttSecurityMode::UsernamePassword,
        );
    }
}
