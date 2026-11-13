<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use InvalidArgumentException;

/**
 * @phpstan-type Component array{
 *     product_id: string,
 *     variant_id: string|null,
 *     inventoryable_type: string,
 *     inventoryable_id: string,
 *     quantity: positive-int,
 *     sku: string,
 *     name: string,
 *     weight: int|null,
 *     dimensions: array{length: int, width: int, height: int}|null,
 *     requires_shipping: bool,
 *     tracks_inventory: bool
 * }
 */
final class CompositeComponents
{
    /** @return list<Component> */
    public static function read(mixed $raw): array
    {
        if (! is_array($raw) || ! array_is_list($raw) || $raw === []) {
            throw new InvalidArgumentException('A composite line requires a non-empty list of components.');
        }

        $components = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                throw new InvalidArgumentException('Invalid composite component snapshot.');
            }

            $productId = $entry['product_id'] ?? null;
            $variantId = $entry['variant_id'] ?? null;
            $type = $entry['inventoryable_type'] ?? null;
            $id = $entry['inventoryable_id'] ?? null;
            $quantity = $entry['quantity'] ?? null;
            $sku = $entry['sku'] ?? null;
            $name = $entry['name'] ?? null;
            $weight = $entry['weight'] ?? null;
            $dimensions = $entry['dimensions'] ?? null;
            $requiresShipping = $entry['requires_shipping'] ?? null;
            $tracksInventory = $entry['tracks_inventory'] ?? null;

            if (! is_string($productId) || $productId === ''
                || ! array_key_exists('variant_id', $entry) || ($variantId !== null && (! is_string($variantId) || $variantId === ''))
                || ! is_string($type) || $type === ''
                || $id !== ($variantId ?? $productId)
                || ! is_int($quantity) || $quantity < 1
                || ! is_string($sku) || $sku === ''
                || ! is_string($name) || $name === ''
                || ! array_key_exists('weight', $entry) || ($weight !== null && (! is_int($weight) || $weight < 1))
                || ! array_key_exists('dimensions', $entry)
                || ! is_bool($requiresShipping) || ! is_bool($tracksInventory)) {
                throw new InvalidArgumentException('Invalid composite component snapshot.');
            }

            if ($dimensions !== null) {
                if (! is_array($dimensions)
                    || ! is_int($dimensions['length'] ?? null) || $dimensions['length'] < 1
                    || ! is_int($dimensions['width'] ?? null) || $dimensions['width'] < 1
                    || ! is_int($dimensions['height'] ?? null) || $dimensions['height'] < 1) {
                    throw new InvalidArgumentException('Invalid composite component dimensions.');
                }

                $dimensions = [
                    'length' => $dimensions['length'],
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                ];
            }

            $components[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'inventoryable_type' => $type,
                'inventoryable_id' => $id,
                'quantity' => $quantity,
                'sku' => $sku,
                'name' => $name,
                'weight' => $weight,
                'dimensions' => $dimensions,
                'requires_shipping' => $requiresShipping,
                'tracks_inventory' => $tracksInventory,
            ];
        }

        return $components;
    }

    /**
     * @param  list<Component>  $components
     * @return list<Component>
     */
    public static function expand(array $components, int $quantity): array
    {
        foreach ($components as &$component) {
            $expanded = $component['quantity'] * $quantity;

            if (! is_int($expanded) || $expanded < 1) {
                throw new InvalidArgumentException('Invalid expanded composite component quantity.');
            }

            $component['quantity'] = $expanded;
        }

        return $components;
    }
}
