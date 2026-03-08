<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Events;

/**
 * Interface for inventory-related events across commerce packages.
 *
 * Extends the base commerce event interface with inventory-specific
 * properties for event sourcing, analytics, and cross-package integration.
 */
interface InventoryEventInterface extends CommerceEventInterface
{
    /**
     * Get the inventoryable model class.
     */
    public function getInventoryableType(): string;

    /**
     * Get the inventoryable model ID.
     */
    public function getInventoryableId(): string | int;

    /**
     * Get the quantity affected by this event.
     */
    public function getQuantity(): int;

    /**
     * Get the location ID if applicable.
     */
    public function getLocationId(): ?string;

    /**
     * Get the cart ID if this event relates to a cart operation.
     */
    public function getCartId(): ?string;

    /**
     * Determine if this event should be persisted to the event store.
     */
    public function shouldPersist(): bool;
}
