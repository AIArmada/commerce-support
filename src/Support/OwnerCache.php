<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
 * // Result: "owner:sha256hash:v1:user.preferences"
 *
 * // Get/put with owner scoping
 * $prefs = OwnerCache::get($owner, 'user.preferences');
 *
 * OwnerCache::put($owner, 'user.preferences', $preferences, CarbonImmutable::now()->addHour());
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
     * Keys embed the owner's current cache version, so forgetOwner()
     * invalidates every key on every driver — including drivers without tag
     * support — by bumping the version.
     *
     * @param  Model|OwnerScopeIdentifiable|null  $owner  The owner model or scope-identifiable object (null = global context)
     * @param  string  $logicalKey  The application logical key (e.g., 'cart.summary')
     * @return string The full scoped cache key (e.g., 'owner:sha256hash:v1:cart.summary')
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

        return 'owner:' . $ownerKey . ':v' . self::version($owner) . ':' . $logicalKey;
    }

    /**
     * Current cache version for the owner. Bumped by forgetOwner().
     */
    public static function version(Model | OwnerScopeIdentifiable | null $owner): int
    {
        return (int) Cache::store()->get(
            self::versionKey(OwnerScopeKey::forOwner($owner)),
            1,
        );
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
     * Rebuilds are guarded by an atomic lock so concurrent misses on an
     * expensive callback produce one rebuild instead of a thundering herd.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function remember(Model | OwnerScopeIdentifiable | null $owner, string $logicalKey, DateTimeInterface | DateInterval | int | null $ttl, callable $callback): mixed
    {
        $repository = self::repository($owner);
        $key = self::key($owner, $logicalKey);

        $cached = $repository->get($key);

        if ($cached !== null) {
            return $cached;
        }

        try {
            return Cache::lock($key . ':lock', 10)->block(5, function () use ($repository, $key, $ttl, $callback): mixed {
                $cached = $repository->get($key);

                if ($cached !== null) {
                    return $cached;
                }

                $value = $callback();

                $repository->put($key, $value, $ttl);

                return $value;
            });
        } catch (LockTimeoutException) {
            return $callback();
        }
    }

    /**
     * Forget an owner-scoped cache key.
     */
    public static function forget(Model | OwnerScopeIdentifiable | null $owner, string $logicalKey): bool
    {
        return self::repository($owner)->forget(self::key($owner, $logicalKey));
    }

    /**
     * Forget all cache keys for an owner.
     *
     * Invalidation is portable: the owner's cache version is bumped so every
     * versioned key stops resolving on any driver. Tag flush is kept as a
     * best-effort memory release on drivers that support tagging.
     */
    public static function forgetOwner(Model | OwnerScopeIdentifiable | null $owner): void
    {
        $ownerKey = OwnerScopeKey::forOwner($owner);

        try {
            Cache::tags([self::tag($ownerKey)])->flush();
        } catch (Throwable) {
            // Driver doesn't support tagging; the version bump below still invalidates.
        }

        $versionKey = self::versionKey($ownerKey);

        Cache::store()->forever($versionKey, (int) Cache::store()->get($versionKey, 1) + 1);
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

    private static function versionKey(string $ownerKey): string
    {
        return "owner:{$ownerKey}:__version";
    }
}
