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
 *     tracks_inventory: bool,
 *     unit_price?: int,
 *     metadata?: array<string, mixed>,
 *     composition_path?: list<array{product_id: string, component_id: string, quantity: positive-int, unit_price?: int, metadata?: array<string, mixed>}>
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
            $unitPrice = $entry['unit_price'] ?? null;
            $metadata = $entry['metadata'] ?? null;
            $compositionPath = $entry['composition_path'] ?? null;

            if (! is_string($productId) || $productId === ''
                || ! array_key_exists('variant_id', $entry) || ($variantId !== null && (! is_string($variantId) || $variantId === ''))
                || ! is_string($type) || $type === ''
                || $id !== ($variantId ?? $productId)
                || ! is_int($quantity) || $quantity < 1
                || ! is_string($sku) || $sku === ''
                || ! is_string($name) || $name === ''
                || ! array_key_exists('weight', $entry) || ($weight !== null && (! is_int($weight) || $weight < 1))
                || ! array_key_exists('dimensions', $entry)
                || ! is_bool($requiresShipping) || ! is_bool($tracksInventory)
                || ($unitPrice !== null && (! is_int($unitPrice) || $unitPrice < 0))
                || ($metadata !== null && ! is_array($metadata))
                || (array_key_exists('composition_path', $entry) && (! is_array($compositionPath) || ! array_is_list($compositionPath) || $compositionPath === []))) {
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

            $component = [
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

            if (array_key_exists('unit_price', $entry)) {
                $component['unit_price'] = $unitPrice;
            }

            if (array_key_exists('metadata', $entry)) {
                $component['metadata'] = $metadata;
            }

            if (array_key_exists('composition_path', $entry)) {
                foreach ($compositionPath as $step) {
                    if (! is_array($step)
                        || ! is_string($step['product_id'] ?? null) || $step['product_id'] === ''
                        || ! is_string($step['component_id'] ?? null) || $step['component_id'] === ''
                        || ! is_int($step['quantity'] ?? null) || $step['quantity'] < 1
                        || (isset($step['unit_price']) && (! is_int($step['unit_price']) || $step['unit_price'] < 0))
                        || (isset($step['metadata']) && ! is_array($step['metadata']))) {
                        throw new InvalidArgumentException('Invalid nested composite provenance path.');
                    }
                }

                $component['composition_path'] = array_map(static function (array $step): array {
                    $normalized = [
                        'product_id' => $step['product_id'],
                        'component_id' => $step['component_id'],
                        'quantity' => $step['quantity'],
                    ];

                    if (array_key_exists('unit_price', $step)) {
                        $normalized['unit_price'] = $step['unit_price'];
                    }

                    if (array_key_exists('metadata', $step)) {
                        $normalized['metadata'] = $step['metadata'];
                    }

                    return $normalized;
                }, $compositionPath);
            }

            $components[] = $component;
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
