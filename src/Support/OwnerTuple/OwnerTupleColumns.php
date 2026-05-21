<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\OwnerTuple;

use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use InvalidArgumentException;

final readonly class OwnerTupleColumns
{
    public function __construct(
        public string $ownerTypeColumn = 'owner_type',
        public string $ownerIdColumn = 'owner_id',
    ) {
        if ($this->ownerTypeColumn === '' || $this->ownerIdColumn === '') {
            throw new InvalidArgumentException('Owner tuple column names must not be empty.');
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    public static function forModelClass(string $modelClass): self
    {
        if (! method_exists($modelClass, 'ownerScopeConfig')) {
            return new self;
        }

        /** @var OwnerScopeConfig $config */
        $config = $modelClass::ownerScopeConfig();

        return new self(
            ownerTypeColumn: $config->ownerTypeColumn,
            ownerIdColumn: $config->ownerIdColumn,
        );
    }

    public static function forConfigKey(string $configKey): self
    {
        return new self(
            ownerTypeColumn: (string) config($configKey . '.owner_type_column', 'owner_type'),
            ownerIdColumn: (string) config($configKey . '.owner_id_column', 'owner_id'),
        );
    }

    /**
     * @param  class-string|null  $modelClass
     */
    public static function forModelClassOrConfigKey(?string $modelClass = null, ?string $configKey = null): self
    {
        if ($modelClass !== null && method_exists($modelClass, 'ownerScopeConfig')) {
            return self::forModelClass($modelClass);
        }

        if ($configKey !== null && $configKey !== '') {
            return self::forConfigKey($configKey);
        }

        return new self;
    }
}
