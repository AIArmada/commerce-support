<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Http\Request;
use Throwable;

/**
 * Request-scoped override for owner-scope behavior.
 *
 * Lets scoped operations (e.g. per-owner batch iteration) suppress
 * include-global matching without mutating global config — config flips are
 * Octane-fragile (a killed worker leaks the flipped value into later
 * requests) and miss models whose scope snapshot was taken at boot.
 *
 * State lives on the current HTTP request attributes bag when available and
 * falls back to guarded static storage otherwise; flushState() resets the
 * fallback at Octane lifecycle boundaries.
 */
final class OwnerScopeOverride
{
    private const string REQUEST_KEY = '__commerce_owner_scope_override__';

    private static bool $fallbackSuppressIncludeGlobal = false;

    /**
     * Run the callback with include-global matching suppressed on every
     * owner scope, restoring the previous state afterwards.
     */
    public static function withoutIncludeGlobal(callable $callback): mixed
    {
        $previous = self::read();

        self::write(true);

        try {
            return $callback();
        } finally {
            self::write($previous);
        }
    }

    public static function suppressIncludeGlobal(): bool
    {
        return self::read();
    }

    /**
     * Flush override state at an application lifecycle boundary.
     *
     * @internal Octane lifecycle integration only.
     */
    public static function flushState(): void
    {
        self::$fallbackSuppressIncludeGlobal = false;

        $request = self::httpRequest();

        if ($request !== null) {
            $request->attributes->remove(self::REQUEST_KEY);
        }
    }

    private static function read(): bool
    {
        $request = self::httpRequest();

        if ($request !== null) {
            return (bool) $request->attributes->get(self::REQUEST_KEY, false);
        }

        return self::$fallbackSuppressIncludeGlobal;
    }

    private static function write(bool $value): void
    {
        $request = self::httpRequest();

        if ($request !== null) {
            $request->attributes->set(self::REQUEST_KEY, $value);

            return;
        }

        self::$fallbackSuppressIncludeGlobal = $value;
    }

    private static function httpRequest(): ?Request
    {
        try {
            $request = app('request');

            return $request instanceof Request ? $request : null;
        } catch (Throwable) {
            return null;
        }
    }
}
