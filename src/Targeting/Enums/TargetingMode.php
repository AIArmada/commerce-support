<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Enums;

/**
 * Targeting evaluation modes.
 */
enum TargetingMode: string
{
    case All = 'all';
    case Any = 'any';
    case Custom = 'custom';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::All => 'All Rules Must Match',
            self::Any => 'Any Rule Must Match',
            self::Custom => 'Custom Expression',
        };
    }

    /**
     * Get description.
     */
    public function description(): string
    {
        return match ($this) {
            self::All => 'Valid only when ALL targeting rules are satisfied (AND logic)',
            self::Any => 'Valid when ANY targeting rule is satisfied (OR logic)',
            self::Custom => 'Use custom boolean expression with AND, OR, NOT operators',
        };
    }
}
