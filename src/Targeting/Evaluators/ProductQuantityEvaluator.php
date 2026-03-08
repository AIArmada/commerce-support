<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;

/**
 * Evaluates targeting rules based on specific product quantity in cart.
 *
 * Unlike CartQuantityEvaluator which checks total quantity,
 * this evaluator checks quantity of specific products.
 *
 * @example
 * ```php
 * // Require at least 3 of SKU-001
 * ['type' => 'product_quantity', 'product' => 'SKU-001', 'operator' => 'gte', 'value' => 3]
 *
 * // Require between 2-5 of any product in list
 * ['type' => 'product_quantity', 'products' => ['SKU-001', 'SKU-002'], 'operator' => 'between', 'min' => 2, 'max' => 5]
 * ```
 */
final readonly class ProductQuantityEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === $this->getType();
    }

    public function getType(): string
    {
        return 'product_quantity';
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $operator = $rule['operator'] ?? 'gte';

        // Get the quantity for the specified product(s)
        $quantity = $this->getQuantity($rule, $context);

        return match ($operator) {
            'eq' => $quantity === (int) ($rule['value'] ?? 0),
            'neq' => $quantity !== (int) ($rule['value'] ?? 0),
            'gt' => $quantity > (int) ($rule['value'] ?? 0),
            'gte' => $quantity >= (int) ($rule['value'] ?? 0),
            'lt' => $quantity < (int) ($rule['value'] ?? 0),
            'lte' => $quantity <= (int) ($rule['value'] ?? 0),
            'between' => $quantity >= (int) ($rule['min'] ?? 0)
                && $quantity <= (int) ($rule['max'] ?? PHP_INT_MAX),
            default => false,
        };
    }

    /**
     * @return array<string>
     */
    public function validate(array $rule): array
    {
        $errors = [];

        // Must have product or products
        if (! isset($rule['product']) && ! isset($rule['products'])) {
            $errors[] = "Rule must have 'product' or 'products' key";
        }

        $operator = $rule['operator'] ?? 'gte';

        if ($operator === 'between') {
            if (! isset($rule['min'])) {
                $errors[] = "Between operator requires 'min' key";
            }
            if (! isset($rule['max'])) {
                $errors[] = "Between operator requires 'max' key";
            }
        } elseif (! isset($rule['value'])) {
            $errors[] = "Rule must have 'value' key";
        }

        return $errors;
    }

    private function getQuantity(array $rule, TargetingContextInterface $context): int
    {
        // Single product
        if (isset($rule['product'])) {
            return $this->getProductQuantity((string) $rule['product'], $context);
        }

        // Multiple products - sum their quantities
        $products = $rule['products'] ?? [];
        $total = 0;

        foreach ($products as $product) {
            $total += $this->getProductQuantity((string) $product, $context);
        }

        return $total;
    }

    private function getProductQuantity(string $identifier, TargetingContextInterface $context): int
    {
        // Use new method if available
        if (method_exists($context, 'getProductQuantity')) {
            return $context->getProductQuantity($identifier);
        }

        // Fallback: iterate cart items
        if (! method_exists($context, 'getCartItems')) {
            return 0;
        }

        $items = $context->getCartItems();
        $quantity = 0;

        foreach ($items as $item) {
            $sku = $item->getAttribute('sku') ?? $item->id ?? null;
            if ($sku === $identifier) {
                $quantity += $item->quantity ?? 1;
            }
        }

        return $quantity;
    }
}
