<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

/**
 * Contract for non-Eloquent objects that can participate in owner-scoped helpers.
 *
 * Eloquent models already satisfy these methods implicitly, so this contract is
 * mainly useful for lightweight adapters, DTOs, or test doubles that need to
 * work with OwnerScopeKey, OwnerCache, or OwnerFilesystem.
 */
interface OwnerScopeIdentifiable
{
    public function getMorphClass(): string;

    public function getKey(): mixed;
}
