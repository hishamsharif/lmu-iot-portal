<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class ReportingApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 500,
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(string $context, Response $response): self
    {
        $payload = $response->json();
        $message = is_array($payload) && is_string($payload['message'] ?? null)
            ? $payload['message']
            : $response->body();
        $message = trim($message) !== '' ? trim($message) : 'Unknown reporting API error.';

        return new self(
            message: "{$context} failed: {$message}",
            status: $response->status(),
            responseBody: $response->body(),
        );
    }
}
