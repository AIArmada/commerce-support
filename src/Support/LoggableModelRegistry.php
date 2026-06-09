<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\Loggable;

final class LoggableModelRegistry
{
    /** @var array<int, class-string<Loggable>> */
    private array $models = [];

    /** @param class-string<Loggable> $modelClass */
    public function register(string $modelClass): void
    {
        if (! in_array($modelClass, $this->models, true)) {
            $this->models[] = $modelClass;
        }
    }

    /** @return array<int, class-string<Loggable>> */
    public function getModels(): array
    {
        return $this->models;
    }
}
