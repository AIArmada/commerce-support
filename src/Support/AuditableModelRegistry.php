<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\Auditable;

final class AuditableModelRegistry
{
    /** @var array<int, class-string<Auditable>> */
    private array $models = [];

    /** @param class-string<Auditable> $modelClass */
    public function register(string $modelClass): void
    {
        if (! in_array($modelClass, $this->models, true)) {
            $this->models[] = $modelClass;
        }
    }

    /** @return array<int, class-string<Auditable>> */
    public function getModels(): array
    {
        return $this->models;
    }
}
