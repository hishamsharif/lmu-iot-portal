<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Publishing\Mqtt;

interface MqttCommandPublisher
{
    /**
     * Publish a command payload to an MQTT topic.
     *
     * Uses the MQTT protocol (not native NATS) so messages are delivered
     * through the NATS MQTT bridge's JetStream path to QoS 1 subscribers.
     */
    public function publish(string $mqttTopic, string $payload, string $host, int $port): void;
}
