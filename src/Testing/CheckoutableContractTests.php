<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Testing;

use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

/**
 * Contract tests for CheckoutableInterface implementations.
 *
 * Use this trait to verify your Cart, Order, or Invoice correctly
 * implements the CheckoutableInterface contract.
 *
 * @example
 * ```php
 * class CartCheckoutTest extends TestCase
 * {
 *     use CheckoutableContractTests;
 *
 *     protected function createCheckoutable(): CheckoutableInterface
 *     {
 *         $cart = Cart::factory()->create();
 *         $cart->addItem(Product::factory()->create(), 2);
 *         return $cart;
 *     }
 * }
 * ```
 */
trait CheckoutableContractTests
{
    /**
     * Create a checkoutable instance for testing.
     */
    abstract protected function createCheckoutable(): CheckoutableInterface;

    public function test_checkoutable_has_line_items(): void
    {
        $checkoutable = $this->createCheckoutable();

        $items = $checkoutable->getCheckoutLineItems();

        expect($items)->toBeIterable();

        foreach ($items as $item) {
            expect($item)->toBeInstanceOf(LineItemInterface::class);
        }
    }

    public function test_checkoutable_has_subtotal(): void
    {
        $checkoutable = $this->createCheckoutable();

        $subtotal = $checkoutable->getCheckoutSubtotal();

        expect($subtotal)->toBeInstanceOf(Money::class);
    }

    public function test_checkoutable_has_discount(): void
    {
        $checkoutable = $this->createCheckoutable();

        $discount = $checkoutable->getCheckoutDiscount();

        expect($discount)->toBeInstanceOf(Money::class);
    }

    public function test_checkoutable_has_tax(): void
    {
        $checkoutable = $this->createCheckoutable();

        $tax = $checkoutable->getCheckoutTax();

        expect($tax)->toBeInstanceOf(Money::class);
    }

    public function test_checkoutable_has_total(): void
    {
        $checkoutable = $this->createCheckoutable();

        $total = $checkoutable->getCheckoutTotal();

        expect($total)->toBeInstanceOf(Money::class);
    }

    public function test_checkoutable_total_is_consistent(): void
    {
        $checkoutable = $this->createCheckoutable();

        $subtotal = $checkoutable->getCheckoutSubtotal()->getAmount();
        $discount = $checkoutable->getCheckoutDiscount()->getAmount();
        $tax = $checkoutable->getCheckoutTax()->getAmount();
        $total = $checkoutable->getCheckoutTotal()->getAmount();

        // Total should equal subtotal - discount + tax
        $expected = $subtotal - $discount + $tax;

        expect($total)->toBe($expected);
    }

    public function test_checkoutable_has_currency(): void
    {
        $checkoutable = $this->createCheckoutable();

        $currency = $checkoutable->getCheckoutCurrency();

        expect($currency)
            ->toBeString()
            ->toHaveLength(3); // ISO 4217 currency code
    }

    public function test_checkoutable_has_reference(): void
    {
        $checkoutable = $this->createCheckoutable();

        $reference = $checkoutable->getCheckoutReference();

        expect($reference)
            ->toBeString()
            ->not->toBeEmpty();
    }

    public function test_checkoutable_notes_is_nullable_string(): void
    {
        $checkoutable = $this->createCheckoutable();

        $notes = $checkoutable->getCheckoutNotes();

        expect($notes)->toBeIn([null, ...array_filter([$notes], 'is_string')]);
    }

    public function test_checkoutable_metadata_is_array(): void
    {
        $checkoutable = $this->createCheckoutable();

        $metadata = $checkoutable->getCheckoutMetadata();

        expect($metadata)->toBeArray();
    }
}
