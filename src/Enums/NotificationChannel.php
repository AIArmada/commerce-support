<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';
    case InApp = 'in_app';
    case Whatsapp = 'whatsapp';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'SMS',
            self::Push => 'Push Notification',
            self::InApp => 'In-App',
            self::Whatsapp => 'WhatsApp',
            self::Telegram => 'Telegram',
        };
    }
}
