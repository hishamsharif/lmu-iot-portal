<?php

declare(strict_types=1);

namespace App\Domain\DeviceManagement\Enums;

enum MqttSecurityMode: string
{
    case UsernamePassword = 'username_password';
    case X509Mtls = 'x509_mtls';

    public function label(): string
    {
        return match ($this) {
            self::UsernamePassword => 'Username / Password',
            self::X509Mtls => 'X.509 mTLS',
        };
    }
}
