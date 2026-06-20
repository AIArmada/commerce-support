<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Enums;

enum NotificationCadence: string
{
    case Off = 'off';
    case Instant = 'instant';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Instant => 'Immediate',
            self::Daily => 'Daily Digest',
            self::Weekly => 'Weekly Digest',
        };
    }
}
