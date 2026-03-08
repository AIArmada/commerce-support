<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final class OwnerContext
{
    public const string CURRENT = '__commerce_support_current_owner__';

    private static bool $hasOverride = false;

    private static ?Model $override = null;

    public static function resolve(): ?Model
    {
        if (self::$hasOverride) {
            return self::$override;
        }

        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        /** @var OwnerResolverInterface $resolver */
        $resolver = app(OwnerResolverInterface::class);

        return $resolver->resolve();
    }

    public static function override(?Model $owner): void
    {
        self::$override = $owner;
        self::$hasOverride = true;
    }

    public static function clearOverride(): void
    {
        self::$override = null;
        self::$hasOverride = false;
    }

    public static function withOwner(?Model $owner, callable $callback): mixed
    {
        $previousOwner = self::$override;
        $previousHasOverride = self::$hasOverride;

        self::$override = $owner;
        self::$hasOverride = true;

        try {
            return $callback();
        } finally {
            self::$override = $previousOwner;
            self::$hasOverride = $previousHasOverride;
        }
    }

    public static function fromTypeAndId(?string $ownerType, string | int | null $ownerId): ?Model
    {
        if ($ownerType === null || $ownerId === null || $ownerType === '' || (string) $ownerId === '') {
            return null;
        }

        $resolved = Relation::getMorphedModel($ownerType) ?? $ownerType;

        if (! class_exists($resolved)) {
            throw new InvalidArgumentException(sprintf('Owner type "%s" could not be resolved to a model class.', $ownerType));
        }

        if (! is_a($resolved, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('Owner type "%s" must resolve to an Eloquent model.', $ownerType));
        }

        /** @var Model $owner */
        $owner = new $resolved;
        $owner->setAttribute($owner->getKeyName(), $ownerId);

        return $owner;
    }
}
