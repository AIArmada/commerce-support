<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

use Illuminate\Support\Facades\Date;

/**
 * Avoid Laravel's failed grammar-format parse for timestamps carrying an
 * explicit offset or fractional seconds.
 *
 * Laravel's Eloquent date casting currently attempts the connection grammar
 * date format before falling back to Date::parse(). PostgreSQL timestamptz
 * values include an offset, so that first attempt cannot succeed.
 *
 * For values that cannot match the grammar format, this trait jumps directly
 * to Laravel's existing Date::parse() fallback. All other values retain the
 * framework's normal asDateTime() behavior.
 *
 * Re-evaluate/remove this trait if Laravel changes asDateTime() to avoid the
 * failed format attempt for offset/fractional timestamps.
 */
trait ParsesPostgresTimestamps
{
    private const OFFSET_OR_FRACTIONAL_SUFFIX =
        '/(?:\.\d+|Z|[+-]\d{2}(?::?\d{2})?)\z/';

    protected function asDateTime($value)
    {
        if (
            is_string($value)
            && preg_match(self::OFFSET_OR_FRACTIONAL_SUFFIX, $value) === 1
        ) {
            // Runtime returns the Date factory's class (immutable here), identical
            // to the parent fallback; the vendor stub assumes mutable Carbon.
            // @phpstan-ignore return.type
            return Date::parse($value);
        }

        return parent::asDateTime($value);
    }
}
