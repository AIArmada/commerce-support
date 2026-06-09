<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use Filament\Panel;

interface CommerceNavigationContributorInterface
{
    /**
     * Register resources, pages, and widgets with the Filament panel.
     */
    public function contribute(Panel $panel): void;
}
