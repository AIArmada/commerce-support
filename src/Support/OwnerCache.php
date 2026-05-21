<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

/**
 * Owner-scoped cache key builder and accessor.
 *
 * Enforces `owner:{ownerScopeKey}:{logicalKey}` pattern to prevent cache bleed
 * across tenants in a shared-cache, single-database multitenancy model.
 *
 * @example
 * ```php
 * // Build an owner-scoped cache key
 * $key = OwnerCache::key($owner, 'user.preferences');
 * // Result: "owner:sha256hash:user.preferences"
 *
 * // Get/put with owner scoping
 * $prefs = OwnerCache::get($owner, 'user.preferences');
 *
 * OwnerCache::put($owner, 'user.preferences', $preferences, now()->addHour());
 *
 * // Forget owner-scoped key
 * OwnerCache::forget($owner, 'user.preferences');
 *
 * // Forget all keys for an owner (by prefix)
 * OwnerCache::forgetOwner($owner);
 * ```
 */
final class OwnerCache
{
    /**
     * Build an owner-scoped cache key.
     *
     * @param  Model|OwnerScopeIdentifiable|null  $owner  The owner model or scope-identifiable object (null = global context)
     * @param  string  $logicalKey  The application logical key (e.g., 'cart.summary')
     * @return string The full scoped cache key (e.g., 'owner:sha256hash:cart.summary')
     *
     * @throws InvalidArgumentException if logicalKey is empty or contains invalid characters
     */
    public static function key(Model | OwnerScopeIdentifiable | null $owner, string $logicalKey): string
    {
        if ($logicalKey === '') {
            throw new InvalidArgumentException('Logical cache key cannot be empty.');
        }

        if (str_contains($logicalKey, ':')) {
            throw new InvalidArgumentException('Logical cache key cannot contain colons. Use dots or dashes instead.');
        }

        $ownerKey = OwnerScopeKey::forOwner($owner);

        return "owner:{$ownerKey}:{$logicalKey}";
    }

    /**
     * Retrieve a value from the owner-scoped cache.
     *
     * @template T
     *
     * @param  T|null  $default
     * @return T|null
     */
    public static function get(Model | OwnerScopeIdentifiable | null $owner, string $logicalKey, mixed $default = null): mixed
    {
        return self::repository($owner)->get(
            self::key($owner, $logicalKey),
            $default
        );
    }

    /**
     * Store a value in the owner-scoped cache.
     */
    public static function put(Model | OwnerScopeIdentifiable | null $owner, string $logicalKey, mixed $value, DateTimeInterface | DateInterval | int | null $ttl = null): void
    {
        self::repository($owner)->put(
            self::key($owner, $logicalKey),
            $value,
            $ttl
        );
    }

    /**
     * Retrieve a value from the cache or store a default.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function remember(Model | OwnerScopeIdentifiable | null $owner, string $logicalKey, DateTimeInterface | DateInterval | int | null $ttl, callable $callback): mixed
    {
        return self::repository($owner)->remember(
            self::key($owner, $logicalKey),
            $ttl,
            $callback
        );
    }

    /**
     * Forget an owner-scoped cache key.
     */
    public static function forget(Model | OwnerScopeIdentifiable | null $owner, string $logicalKey): bool
    {
        return self::repository($owner)->forget(self::key($owner, $logicalKey));
    }

    /**
     * Forget all cache keys for an owner by prefix.
     *
     * Note: This uses the cache driver's tag support if available, otherwise
     * it is a no-op for drivers that don't support tagged cache groups.
     * For production, ensure your cache driver (Redis, Memcached) supports tagging.
     */
    public static function forgetOwner(Model | OwnerScopeIdentifiable | null $owner): void
    {
        $ownerKey = OwnerScopeKey::forOwner($owner);

        try {
            Cache::tags([self::tag($ownerKey)])->flush();
        } catch (Throwable) {
            // Driver doesn't support tagging; app should use explicit forget() instead.
        }
    }

    private static function repository(Model | OwnerScopeIdentifiable | null $owner): mixed
    {
        $ownerKey = OwnerScopeKey::forOwner($owner);

        try {
            return Cache::tags([self::tag($ownerKey)]);
        } catch (Throwable) {
            return Cache::store();
        }
    }

    private static function tag(string $ownerKey): string
    {
        return "owner:{$ownerKey}";
    }
}
