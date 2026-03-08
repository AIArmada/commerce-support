<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

/**
 * Trait for caching computed values within a single request lifecycle.
 *
 * Uses a static cache array to store computed values per instance+method,
 * avoiding redundant expensive computations during a single request.
 *
 * @example
 * ```php
 * public function getDefaultTier(): ?AffiliateProgramTier
 * {
 *     return $this->cachedComputation(__METHOD__, fn () =>
 *         $this->tiers()->orderBy('level', 'desc')->first()
 *     );
 * }
 * ```
 */
trait CachesComputedValues // @phpstan-ignore trait.unused
{
    /**
     * Static cache storage for computed values.
     *
     * @var array<string, mixed>
     */
    private static array $computedCache = [];

    /**
     * Cache a computed value for the request lifetime.
     *
     * @template T
     *
     * @param  string  $key  Unique key for this computation (use __METHOD__ for simplicity)
     * @param  callable(): T  $callback  The computation to cache
     * @return T
     */
    protected function cachedComputation(string $key, callable $callback): mixed
    {
        $cacheKey = $this->getCacheKey($key);

        if (! array_key_exists($cacheKey, self::$computedCache)) {
            self::$computedCache[$cacheKey] = $callback();
        }

        return self::$computedCache[$cacheKey];
    }

    /**
     * Cache a computed value with model instance specificity.
     *
     * Use this when the same method on different model instances
     * should have separate cached values.
     *
     * @template T
     *
     * @param  string  $method  The method name (use __METHOD__)
     * @param  callable(): T  $callback  The computation to cache
     * @return T
     */
    protected function cachedForInstance(string $method, callable $callback): mixed
    {
        return $this->cachedComputation($method, $callback);
    }

    /**
     * Clear all cached computations (useful for testing).
     */
    public static function clearComputedCache(): void
    {
        self::$computedCache = [];
    }

    /**
     * Generate a unique cache key for this instance + method combination.
     */
    private function getCacheKey(string $method): string
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        $instanceId = method_exists($this, 'getKey') ? $this->getKey() : spl_object_id($this);

        return static::class . ':' . $instanceId . ':' . $method;
    }
}
