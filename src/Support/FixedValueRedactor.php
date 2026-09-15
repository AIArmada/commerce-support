<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use OwenIt\Auditing\Contracts\AttributeRedactor;

/**
 * Redact an audited attribute to a fixed marker.
 *
 * Wire it through owen-it's `$attributeModifiers` for credentials, tokens,
 * account numbers, and other values that must never land in audit storage.
 */
final class FixedValueRedactor implements AttributeRedactor
{
    public static function redact(mixed $value): string
    {
        return '[REDACTED]';
    }
}
