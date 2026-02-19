<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Domain\Shared\Models\Organization;
use Database\Factories\Domain\Reporting\Models\OrganizationReportSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationReportSetting extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Reporting\Models\OrganizationReportSettingFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): OrganizationReportSettingFactory
    {
        return OrganizationReportSettingFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift_schedules' => 'array',
            'max_range_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
