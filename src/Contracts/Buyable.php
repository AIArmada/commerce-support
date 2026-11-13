<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

/**
 * Canonical contract for an item that can be sold as a cart line.
 *
 * Prices are integer minor units. The identifier is the stable identity of the
 * actual sellable record (for example a variant), while the catalog identifier
 * points at its catalog parent (for example the product).
 *
 * Implementations may expose the following canonical keys through
 * {@see self::getBuyableAttributes()}:
 *
 * - `sku`: string|null
 * - `weight`: int|null, normalized grams
 * - `dimensions`: array{length: int, width: int, height: int}|null, normalized centimetres
 * - `requires_shipping`: bool
 * - `is_taxable`: bool
 * - `tax_class`: string|null
 * - `inventoryable_type`, `inventoryable_id`: optional inventory identity
 * - `product_id`, `variant_id`: optional catalog identity
 */
interface Buyable
{
    /**
     * Get the stable identity of the actual sellable record.
     */
    public function getBuyableIdentifier(): string;

    /**
     * Get the catalog parent identity, when it differs from the sellable identity.
     */
    public function getBuyableCatalogIdentifier(): ?string;

    /**
     * Get the display title for the sellable line.
     */
    public function getBuyableName(): string;

    /**
     * Get the unit price in integer minor units.
     */
    public function getBuyablePrice(): int;

    /**
     * Get the ISO 4217 currency for the price.
     */
    public function getBuyableCurrency(): string;

    /**
     * Get canonical line attributes used by cart, tax, and shipping boundaries.
     *
     * @return array<string, mixed>
     */
    public function getBuyableAttributes(): array;

    /**
     * Check whether the sellable can be purchased for the given total quantity.
     */
    public function canBePurchased(?int $quantity = null): bool;

    /**
     * Get the available stock, or null when inventory is not tracked.
     */
    public function getBuyableStock(): ?int;

    public function getBuyableMinimumQuantity(): int;

    public function getBuyableMaximumQuantity(): ?int;

    public function getBuyableQuantityIncrement(): int;
}
