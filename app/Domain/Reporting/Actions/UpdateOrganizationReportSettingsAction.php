<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Models\OrganizationReportSetting;
use App\Domain\Reporting\Services\ReportingApiClient;
use RuntimeException;

class UpdateOrganizationReportSettingsAction
{
    public function __construct(
        private readonly ReportingApiClient $reportingApiClient,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(array $payload): OrganizationReportSetting
    {
        $response = $this->reportingApiClient->updateOrganizationSettings($payload);
        $organizationIdValue = data_get($response, 'data.organization_id');
        $organizationId = is_numeric($organizationIdValue) ? (int) $organizationIdValue : 0;

        $settings = OrganizationReportSetting::query()
            ->where('organization_id', $organizationId)
            ->first();

        if (! $settings instanceof OrganizationReportSetting) {
            throw new RuntimeException('Organization report settings update failed.');
        }

        return $settings;
    }
}
