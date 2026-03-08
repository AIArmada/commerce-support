<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use Spatie\Health\Checks\Check;

/**
 * Interface for services/packages that provide health checks.
 */
interface HasHealthCheck
{
    /**
     * Get the health check(s) for this service.
     *
     * @return array<int, Check>
     */
    public function getHealthChecks(): array;
}
