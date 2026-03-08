<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Events;

/**
 * Interface for cart-specific events.
 *
 * Extends the base commerce event interface with cart-specific methods.
 */
interface CartEventInterface extends CommerceEventInterface
{
    /**
     * Get the cart identifier this event belongs to.
     *
     * @return string Cart identifier (user ID, session ID, etc.)
     */
    public function getCartIdentifier(): string;

    /**
     * Get the cart instance name.
     *
     * @return string Instance name (e.g., 'default', 'wishlist', 'saved-for-later')
     */
    public function getCartInstance(): string;

    /**
     * Get the cart ID (UUID) if available.
     *
     * @return string|null Cart primary key UUID
     */
    public function getCartId(): ?string;
}
