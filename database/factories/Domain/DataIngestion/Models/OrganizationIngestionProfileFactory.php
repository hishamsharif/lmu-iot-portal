<?php

declare(strict_types=1);

namespace Database\Factories\Domain\DataIngestion\Models;

use App\Domain\DataIngestion\Models\OrganizationIngestionProfile;
use App\Domain\Shared\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationIngestionProfile>
 */
class OrganizationIngestionProfileFactory extends Factory
{
    protected $model = OrganizationIngestionProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'raw_retention_days' => 90,
            'debug_log_retention_days' => 14,
            'soft_msgs_per_minute' => 60_000,
            'soft_storage_mb_per_day' => 2_048,
            'tier' => 'standard',
        ];
    }
}
