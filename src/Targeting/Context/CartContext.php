<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Context;

use Illuminate\Support\Collection;

/**
 * Cart-specific context for targeting evaluation.
 *
 * Encapsulates all cart-related data used in targeting rules:
 * - Cart value and quantity
 * - Product identifiers (SKUs)
 * - Product categories
 * - Cart items and metadata
 */
readonly class CartContext
{
    /**
     * @param  int  $value  Cart subtotal in minor units (cents)
     * @param  int  $quantity  Total item quantity
     * @param  array<string>  $productIdentifiers  SKUs or product IDs
     * @param  array<string>  $productCategories  Category slugs
     * @param  array<string, mixed>  $metadata  Cart metadata
     * @param  Collection<int, mixed>|null  $items  Cart items collection
     */
    public function __construct(
        public int $value = 0,
        public int $quantity = 0,
        public array $productIdentifiers = [],
        public array $productCategories = [],
        public array $metadata = [],
        public ?Collection $items = null,
    ) {}

    /**
     * Create context from a cart instance.
     */
    public static function fromCart(mixed $cart): self
    {
        if ($cart === null) {
            return new self;
        }

        return new self(
            value: self::extractCartValue($cart),
            quantity: self::extractCartQuantity($cart),
            productIdentifiers: self::extractProductIdentifiers($cart),
            productCategories: self::extractProductCategories($cart),
            metadata: self::extractCartMetadata($cart),
            items: self::extractCartItems($cart),
        );
    }

    public function hasProduct(string $identifier): bool
    {
        return in_array($identifier, $this->productIdentifiers, true);
    }

    public function hasCategory(string $category): bool
    {
        return in_array($category, $this->productCategories, true);
    }

    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get quantity of a specific product in cart.
     */
    public function getProductQuantity(string $identifier): int
    {
        if ($this->items === null) {
            return 0;
        }

        return $this->items
            ->filter(function ($item) use ($identifier): bool {
                $sku = $item->getAttribute('sku') ?? $item->id ?? null;

                return $sku === $identifier;
            })
            ->sum(fn ($item) => $item->quantity ?? 1);
    }

    private static function extractCartValue(mixed $cart): int
    {
        if (method_exists($cart, 'getRawSubtotalWithoutConditions')) {
            return $cart->getRawSubtotalWithoutConditions();
        }

        if (method_exists($cart, 'getSubtotal')) {
            return (int) $cart->getSubtotal();
        }

        return 0;
    }

    private static function extractCartQuantity(mixed $cart): int
    {
        if (method_exists($cart, 'getItems')) {
            $items = $cart->getItems();
            if ($items instanceof Collection) {
                return $items->sum(fn ($item) => $item->quantity ?? 1);
            }
        }

        if (method_exists($cart, 'getTotalQuantity')) {
            return $cart->getTotalQuantity();
        }

        return 0;
    }

    /**
     * @return array<string>
     */
    private static function extractProductIdentifiers(mixed $cart): array
    {
        if (! method_exists($cart, 'getItems')) {
            return [];
        }

        $items = $cart->getItems();
        if (! $items instanceof Collection) {
            return [];
        }

        return $items
            ->map(function ($item): ?string {
                $sku = $item->getAttribute('sku') ?? null;
                if ($sku !== null) {
                    return (string) $sku;
                }

                $model = $item->associatedModel ?? null;
                if ($model === null) {
                    return $item->id ?? null;
                }

                if (method_exists($model, 'getSku')) {
                    return $model->getSku();
                }

                if (is_object($model) && property_exists($model, 'sku')) {
                    return $model->sku;
                }

                return $item->id ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string>
     */
    private static function extractProductCategories(mixed $cart): array
    {
        if (! method_exists($cart, 'getItems')) {
            return [];
        }

        $items = $cart->getItems();
        if (! $items instanceof Collection) {
            return [];
        }

        return $items
            ->flatMap(function ($item): array {
                $category = $item->getAttribute('category') ?? null;
                if ($category !== null) {
                    return is_array($category) ? $category : [(string) $category];
                }

                $model = $item->associatedModel ?? null;
                if ($model === null) {
                    return [];
                }

                if (method_exists($model, 'getCategories')) {
                    return $model->getCategories();
                }

                if (is_object($model) && property_exists($model, 'category')) {
                    return [$model->category];
                }

                return [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractCartMetadata(mixed $cart): array
    {
        if (method_exists($cart, 'getMetadata')) {
            $metadata = $cart->getMetadata();

            return is_array($metadata) ? $metadata : [];
        }

        return [];
    }

    /**
     * @return Collection<int, mixed>|null
     */
    private static function extractCartItems(mixed $cart): ?Collection
    {
        if (! method_exists($cart, 'getItems')) {
            return null;
        }

        $items = $cart->getItems();

        return $items instanceof Collection ? $items : null;
    }
}
