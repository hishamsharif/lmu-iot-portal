<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Models;

use App\Domain\Shared\Models\User;
use Database\Factories\Domain\DeviceManagement\Models\DeviceCertificateFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DeviceCertificate extends Model
{
    /** @use HasFactory<DeviceCertificateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): DeviceCertificateFactory
    {
        return DeviceCertificateFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'not_before' => 'datetime',
            'not_after' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        $notAfter = $this->getAttribute('not_after');

        return ! $notAfter instanceof \DateTimeInterface
            ? true
            : $notAfter < now();
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function decryptedPrivateKey(): ?string
    {
        $encryptedPrivateKey = trim($this->private_key_encrypted);

        if ($encryptedPrivateKey === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedPrivateKey);
        } catch (DecryptException) {
            return null;
        }
    }
}
