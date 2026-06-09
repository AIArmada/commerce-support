<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\Filament;

use AIArmada\CommerceSupport\Contracts\CommerceNavigationContributorInterface;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Contracts\Container\Container;

final class CommerceNavigationPlugin implements Plugin
{
    public function __construct(
        private readonly Container $container,
    ) {}

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

        foreach ($this->container->tagged('commerce.navigation.contributors') as $contributor) {
            if ($contributor instanceof CommerceNavigationContributorInterface) {
                $contributor->contribute($panel);
            }
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
