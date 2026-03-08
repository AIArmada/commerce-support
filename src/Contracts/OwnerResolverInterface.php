<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for resolving the current owner for multi-tenancy support.
 *
 * This interface provides a standardized way to resolve the current owner
 * (merchant, tenant, store, etc.) across all commerce packages. Implementations
 * should return the current owner based on the application's tenancy strategy.
 *
 * @example
 * ```php
 * class AppOwnerResolver implements OwnerResolverInterface
 * {
 *     public function resolve(): ?Model
 *     {
 *         // Using Spatie multitenancy
 *         return Tenant::current();
 *
 *         // Using Filament panels
 *         return Filament::getTenant();
 *
 *         // Using authenticated user's store
 *         return auth()->user()?->currentStore;
 *     }
 * }
 * ```
 */
interface OwnerResolverInterface
{
    /**
     * Resolve the current owner.
     *
     * @return Model|null The owner model, or null if no owner context exists
     */
    public function resolve(): ?Model;
}
