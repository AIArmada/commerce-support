<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Health;

use AIArmada\CommerceSupport\Contracts\HasHealthCheck;
use Illuminate\Contracts\Container\Container;
use Spatie\Health\Checks\Check;

final class HealthCheckRegistry
{
    private bool $resolved = false;

    /** @var array<int, Check> */
    private array $checks = [];

    /** @var list<class-string<HasHealthCheck>> */
    private array $providers = [];

    public function __construct(
        private readonly Container $app,
    ) {}

    public function registerProvider(string $abstract): void
    {
        $this->providers[] = $abstract;
        $this->resolved = false;
    }

    /** @return array<int, Check> */
    public function getChecks(): array
    {
        if (! $this->resolved) {
            $this->resolve();
        }

        return $this->checks;
    }

    private function resolve(): void
    {
        $this->checks = [];
        $this->resolved = true;

        foreach ($this->providers as $abstract) {
            if (! $this->app->bound($abstract) && ! class_exists($abstract)) {
                continue;
            }

            $instance = $this->app->make($abstract);

            if (! $instance instanceof HasHealthCheck) {
                continue;
            }

            foreach ($instance->getHealthChecks() as $check) {
                if ($check instanceof Check) {
                    $this->checks[] = $check;
                }
            }
        }
    }
}
