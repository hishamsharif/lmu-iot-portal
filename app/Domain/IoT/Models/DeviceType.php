<?php

declare(strict_types=1);

namespace App\Domain\IoT\Models;

use App\Domain\IoT\Casts\ProtocolConfigCast;
use App\Domain\IoT\DeviceTypes\ValueObjects\Protocol\ProtocolConfigInterface;
use App\Domain\IoT\Enums\ProtocolType;
use App\Domain\Shared\Models\Organization;
use Database\Factories\DeviceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ProtocolConfigInterface|null $protocol_config
 * @property ProtocolType $default_protocol
 */
class DeviceType extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceTypeFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): DeviceTypeFactory
    {
        return DeviceTypeFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_protocol' => ProtocolType::class,
            'protocol_config' => ProtocolConfigCast::class,
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if this is a global catalog entry.
     */
    public function isGlobal(): bool
    {
        return $this->organization_id === null;
    }

    /**
     * Get the protocol configuration as a typed object.
     */
    public function getProtocolConfig(): ?ProtocolConfigInterface
    {
        return $this->protocol_config;
    }

    /**
     * Scope to only global catalog entries.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('organization_id');
    }

    /**
     * Scope to organization-specific entries.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeForOrganization($query, int|Organization $organization)
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $query->where('organization_id', $organizationId);
    }
}
