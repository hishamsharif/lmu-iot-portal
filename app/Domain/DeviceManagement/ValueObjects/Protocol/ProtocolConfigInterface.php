<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\ValueObjects\Protocol;

interface ProtocolConfigInterface
{
    /**
     * Get the base topic prefix for MQTT topics.
     *
     * Full topic is: {baseTopic}/{device_uuid}/{suffix}
     */
    public function getBaseTopic(): ?string;

    /**
     * Convert the configuration to an array for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Create an instance from an array (deserialization).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static;
}
