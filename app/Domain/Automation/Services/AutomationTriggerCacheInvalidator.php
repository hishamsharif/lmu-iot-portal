<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use Illuminate\Cache\CacheManager;

class AutomationTriggerCacheInvalidator
{
    private const CACHE_VERSION_KEY = 'automation:trigger-matcher:cache-version';

    public function __construct(private readonly CacheManager $cacheManager) {}

    public function currentVersion(): int
    {
        $currentVersion = $this->cacheManager->store()->get(self::CACHE_VERSION_KEY);

        if (is_int($currentVersion) && $currentVersion > 0) {
            return $currentVersion;
        }

        if (is_numeric($currentVersion) && (int) $currentVersion > 0) {
            return (int) $currentVersion;
        }

        $this->cacheManager->store()->forever(self::CACHE_VERSION_KEY, 1);

        return 1;
    }

    public function bumpVersion(): int
    {
        $currentVersion = $this->currentVersion();
        $nextVersion = $currentVersion + 1;

        $this->cacheManager->store()->forever(self::CACHE_VERSION_KEY, $nextVersion);

        return $nextVersion;
    }
}
