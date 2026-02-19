<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Exceptions\ReportingApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ReportingApiClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createReportRun(array $payload): array
    {
        $response = $this->request()
            ->asJson()
            ->post($this->endpoint('/report-runs'), $payload);

        if (! $response->successful()) {
            throw ReportingApiException::fromResponse('Create report run', $response);
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    public function deleteReportRun(int $reportRunId, int $organizationId): void
    {
        $response = $this->request()
            ->delete($this->endpoint("/report-runs/{$reportRunId}"), [
                'organization_id' => $organizationId,
            ]);

        if (! $response->successful()) {
            throw ReportingApiException::fromResponse('Delete report run', $response);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateOrganizationSettings(array $payload): array
    {
        $response = $this->request()
            ->asJson()
            ->put($this->endpoint('/organization-report-settings'), $payload);

        if (! $response->successful()) {
            throw ReportingApiException::fromResponse('Update report settings', $response);
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    public function downloadReportRun(int $reportRunId, int $organizationId): Response
    {
        $response = $this->request()
            ->accept('text/csv')
            ->get($this->endpoint("/report-runs/{$reportRunId}/download"), [
                'organization_id' => $organizationId,
            ]);

        if (! $response->successful()) {
            throw ReportingApiException::fromResponse('Download report run', $response);
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('reporting.api.base_url', config('app.url')), '/');
        $timeoutSeconds = (int) config('reporting.api.timeout_seconds', 30);
        $token = (string) config('reporting.api.token', '');
        $tokenHeader = (string) config('reporting.api.token_header', 'X-Reporting-Token');

        if ($token === '') {
            throw new ReportingApiException('Reporting API token is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->timeout($timeoutSeconds)
            ->withHeaders([$tokenHeader => $token]);
    }

    private function endpoint(string $path): string
    {
        return '/api/internal/reporting'.(str_starts_with($path, '/') ? $path : "/{$path}");
    }
}
