<?php

declare(strict_types=1);

namespace App\Domain\IoT\Contracts;

interface ProtocolConfigInterface
{
    /**
     * Validate the protocol configuration.
     */
    public function validate(): bool;

    /**
     * Get the telemetry topic template for device data ingestion.
     */
    public function getTelemetryTopicTemplate(): string;

    /**
     * Get the control topic template for device commands (downlink).
     */
    public function getControlTopicTemplate(): ?string;

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
