<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Persistence;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Stores opaque cache-busting versions so a namespace can invalidate all of
 * its derived entries without flushing the shared cache store.
 */
final class CacheVersionStore
{
    public function version(string $key): string
    {
        $candidate = (string) Str::ulid();

        if (Cache::add($key, $candidate)) {
            return $candidate;
        }

        $version = Cache::get($key);

        return is_string($version) && $version !== '' ? $version : $candidate;
    }

    public function bump(string $key): void
    {
        Cache::forever($key, (string) Str::ulid());
    }
}
