<?php

declare(strict_types=1);

use App\Domain\Shared\Models\Organization;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds only one organization and stays idempotent', function (): void {
    $this->seed(OrganizationSeeder::class);
    $this->seed(OrganizationSeeder::class);

    $organizations = Organization::query()->get();

    expect($organizations)->toHaveCount(1)
        ->and($organizations->first()?->slug)->toBe('main-organization');
});
