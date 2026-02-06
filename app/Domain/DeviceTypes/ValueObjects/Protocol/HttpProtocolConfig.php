<?php

declare(strict_types=1);

namespace App\Domain\DeviceTypes\ValueObjects\Protocol;

use App\Domain\DeviceTypes\Enums\HttpAuthType;

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
        if (empty($this->baseUrl) || ! filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Base URL must be a valid URL');
        }

        if (! in_array($this->method, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
            throw new \InvalidArgumentException('HTTP method must be GET, POST, PUT, or PATCH');
        }

        if ($this->timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than 0');
        }

        if ($this->authType === HttpAuthType::Bearer && empty($this->authToken)) {
            throw new \InvalidArgumentException('Bearer token is required');
        }

        if ($this->authType === HttpAuthType::Basic && (empty($this->authUsername) || empty($this->authPassword))) {
            throw new \InvalidArgumentException('Basic auth username and password are required');
        }
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
        $baseUrl = $data['base_url'] ?? null;
        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new \InvalidArgumentException('base_url is required');
        }

        $telemetryEndpoint = $data['telemetry_endpoint'] ?? null;
        $telemetryEndpoint = is_string($telemetryEndpoint) ? $telemetryEndpoint : '/telemetry';

        $controlEndpoint = $data['control_endpoint'] ?? null;
        $controlEndpoint = is_string($controlEndpoint) ? $controlEndpoint : null;

        $method = $data['method'] ?? null;
        $method = is_string($method) ? $method : 'POST';

        $headers = $data['headers'] ?? [];
        $headers = is_array($headers) ? $headers : [];
        /** @var array<string, string> $headers */
        $headers = array_map(
            static fn ($value): string => is_scalar($value) ? (string) $value : '',
            $headers
        );

        $authTypeValue = $data['auth_type'] ?? null;
        $authType = $authTypeValue instanceof HttpAuthType
            ? $authTypeValue
            : (is_string($authTypeValue) || is_int($authTypeValue)
                ? HttpAuthType::from($authTypeValue)
                : HttpAuthType::None);

        $timeoutValue = $data['timeout'] ?? 30;
        $timeout = is_numeric($timeoutValue) ? (int) $timeoutValue : 30;

        return new self(
            baseUrl: $baseUrl,
            telemetryEndpoint: $telemetryEndpoint,
            controlEndpoint: $controlEndpoint,
            method: $method,
            headers: $headers,
            authType: $authType,
            authToken: isset($data['auth_token']) && is_string($data['auth_token']) ? $data['auth_token'] : null,
            authUsername: isset($data['auth_username']) && is_string($data['auth_username']) ? $data['auth_username'] : null,
            authPassword: isset($data['auth_password']) && is_string($data['auth_password']) ? $data['auth_password'] : null,
            timeout: $timeout,
        );
    }
}
