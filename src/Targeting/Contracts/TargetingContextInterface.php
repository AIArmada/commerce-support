<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Contract for targeting evaluation context.
 */
interface TargetingContextInterface
{
    /**
     * Get the cart instance.
     */
    public function getCart(): mixed;

    /**
     * Get the user model.
     */
    public function getUser(): ?Model;

    /**
     * Get the HTTP request.
     */
    public function getRequest(): ?Request;

    /**
     * Get user segments/groups.
     *
     * @return array<string>
     */
    public function getUserSegments(): array;

    /**
     * Get a user attribute value.
     */
    public function getUserAttribute(string $attribute): mixed;

    /**
     * Check if this is the user's first purchase.
     */
    public function isFirstPurchase(): bool;

    /**
     * Get customer lifetime value in minor units.
     */
    public function getCustomerLifetimeValue(): int;

    /**
     * Get cart subtotal in minor units.
     */
    public function getCartValue(): int;

    /**
     * Get total quantity of items in cart.
     */
    public function getCartQuantity(): int;

    /**
     * Get product SKUs/IDs in cart.
     *
     * @return array<string>
     */
    public function getProductIdentifiers(): array;

    /**
     * Get categories of products in cart.
     *
     * @return array<string>
     */
    public function getProductCategories(): array;

    /**
     * Get the current channel (web, mobile, api, pos, etc).
     */
    public function getChannel(): string;

    /**
     * Get the device type (desktop, mobile, tablet).
     */
    public function getDevice(): string;

    /**
     * Get the country code (ISO 3166-1 alpha-2).
     */
    public function getCountry(): ?string;

    /**
     * Get the referrer URL or source.
     */
    public function getReferrer(): ?string;

    /**
     * Get current time in the specified or detected timezone.
     */
    public function getCurrentTime(?string $timezone = null): Carbon;

    /**
     * Get the timezone for time-based rules.
     */
    public function getTimezone(): string;

    /**
     * Get custom metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed;
}
