<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class CommerceNavigationPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'commerce-navigation';
    }

    public function register(Panel $panel): void
    {
        CommerceNavigation::configurePanel($panel);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
