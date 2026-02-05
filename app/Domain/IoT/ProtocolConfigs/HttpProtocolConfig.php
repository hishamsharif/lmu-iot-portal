<?php

declare(strict_types=1);

namespace App\Domain\IoT\ProtocolConfigs;

use App\Domain\IoT\Contracts\ProtocolConfigInterface;
use App\Domain\IoT\Enums\HttpAuthType;

final readonly class HttpProtocolConfig implements ProtocolConfigInterface
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $baseUrl,
        public string $telemetryEndpoint = '/telemetry',
        public ?string $controlEndpoint = null,
        public string $method = 'POST',
        public array $headers = [],
        public HttpAuthType $authType = HttpAuthType::None,
        public ?string $authToken = null,
        public ?string $authUsername = null,
        public ?string $authPassword = null,
        public int $timeout = 30,
    ) {
    }

    public function validate(): bool
    {
        if (empty($this->baseUrl) || !filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        if (!in_array($this->method, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
            return false;
        }

        if ($this->timeout <= 0) {
            return false;
        }

        // Validate auth requirements
        if ($this->authType === HttpAuthType::Bearer && empty($this->authToken)) {
            return false;
        }

        if ($this->authType === HttpAuthType::Basic && (empty($this->authUsername) || empty($this->authPassword))) {
            return false;
        }

        return true;
    }

    public function getTelemetryTopicTemplate(): string
    {
        return rtrim($this->baseUrl, '/').'/'.$this->telemetryEndpoint;
    }

    public function getControlTopicTemplate(): ?string
    {
        if ($this->controlEndpoint === null) {
            return null;
        }

        return rtrim($this->baseUrl, '/').'/'.$this->controlEndpoint;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'telemetry_endpoint' => $this->telemetryEndpoint,
            'control_endpoint' => $this->controlEndpoint,
            'method' => $this->method,
            'headers' => $this->headers,
            'auth_type' => $this->authType->value,
            'auth_token' => $this->authToken,
            'auth_username' => $this->authUsername,
            'auth_password' => $this->authPassword,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            baseUrl: $data['base_url'] ?? throw new \InvalidArgumentException('base_url is required'),
            telemetryEndpoint: $data['telemetry_endpoint'] ?? '/telemetry',
            controlEndpoint: $data['control_endpoint'] ?? null,
            method: $data['method'] ?? 'POST',
            headers: $data['headers'] ?? [],
            authType: isset($data['auth_type']) ? HttpAuthType::from($data['auth_type']) : HttpAuthType::None,
            authToken: $data['auth_token'] ?? null,
            authUsername: $data['auth_username'] ?? null,
            authPassword: $data['auth_password'] ?? null,
            timeout: $data['timeout'] ?? 30,
        );
    }
}
