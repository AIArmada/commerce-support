<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Testing;

use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;

/**
 * Contract tests for PaymentGatewayInterface implementations.
 *
 * Use this trait in your gateway's test class to verify it correctly
 * implements the PaymentGatewayInterface contract.
 *
 * @example
 * ```php
 * class StripeGatewayTest extends TestCase
 * {
 *     use PaymentGatewayContractTests;
 *
 *     protected function getGateway(): PaymentGatewayInterface
 *     {
 *         return new StripeGateway(config('services.stripe.secret'));
 *     }
 *
 *     protected function createCheckoutable(int $amount = 10000): CheckoutableInterface
 *     {
 *         return new TestCheckoutable($amount);
 *     }
 * }
 * ```
 */
trait PaymentGatewayContractTests
{
    /**
     * Get the gateway implementation to test.
     */
    abstract protected function getGateway(): PaymentGatewayInterface;

    /**
     * Create a checkoutable object for testing.
     */
    abstract protected function createCheckoutable(int $amount = 10000): CheckoutableInterface;

    /**
     * Create a customer object for testing.
     */
    protected function createCustomer(): ?CustomerInterface
    {
        return null;
    }

    /**
     * Skip tests that require real API calls.
     */
    protected function shouldSkipApiTests(): bool
    {
        return true;
    }

    public function test_gateway_has_name(): void
    {
        $gateway = $this->getGateway();

        expect($gateway->getName())
            ->toBeString()
            ->not->toBeEmpty();
    }

    public function test_gateway_has_display_name(): void
    {
        $gateway = $this->getGateway();

        expect($gateway->getDisplayName())
            ->toBeString()
            ->not->toBeEmpty();
    }

    public function test_gateway_reports_test_mode(): void
    {
        $gateway = $this->getGateway();

        expect($gateway->isTestMode())->toBeBool();
    }

    public function test_gateway_supports_returns_boolean(): void
    {
        $gateway = $this->getGateway();

        $features = ['refunds', 'partial_refunds', 'webhooks', 'hosted_checkout'];

        foreach ($features as $feature) {
            expect($gateway->supports($feature))->toBeBool();
        }
    }

    public function test_gateway_has_webhook_handler(): void
    {
        $gateway = $this->getGateway();

        expect($gateway->getWebhookHandler())
            ->toBeInstanceOf(WebhookHandlerInterface::class);
    }

    public function test_create_payment_returns_payment_intent(): void
    {
        if ($this->shouldSkipApiTests()) {
            $this->markTestSkipped('Skipping API test');
        }

        $gateway = $this->getGateway();
        $checkoutable = $this->createCheckoutable(10000);
        $customer = $this->createCustomer();

        $intent = $gateway->createPayment($checkoutable, $customer);

        expect($intent)
            ->toBeInstanceOf(PaymentIntentInterface::class)
            ->and($intent->getPaymentId())->not->toBeEmpty()
            ->and($intent->getStatus())->toBeInstanceOf(PaymentStatus::class);
    }

    public function test_get_payment_returns_payment_intent(): void
    {
        if ($this->shouldSkipApiTests()) {
            $this->markTestSkipped('Skipping API test');
        }

        $gateway = $this->getGateway();
        $checkoutable = $this->createCheckoutable(10000);

        $created = $gateway->createPayment($checkoutable);
        $retrieved = $gateway->getPayment($created->getPaymentId());

        expect($retrieved)
            ->toBeInstanceOf(PaymentIntentInterface::class)
            ->and($retrieved->getPaymentId())->toBe($created->getPaymentId());
    }

    public function test_get_payment_throws_for_invalid_id(): void
    {
        if ($this->shouldSkipApiTests()) {
            $this->markTestSkipped('Skipping API test');
        }

        $gateway = $this->getGateway();

        $this->expectException(PaymentGatewayException::class);

        $gateway->getPayment('invalid-payment-id-' . uniqid());
    }

    public function test_cancel_payment_returns_cancelled_status(): void
    {
        if ($this->shouldSkipApiTests()) {
            $this->markTestSkipped('Skipping API test');
        }

        $gateway = $this->getGateway();

        if (! $gateway->supports('cancellation')) {
            $this->markTestSkipped('Gateway does not support cancellation');
        }

        $checkoutable = $this->createCheckoutable(10000);
        $created = $gateway->createPayment($checkoutable);

        if (! $created->getStatus()->isCancellable()) {
            $this->markTestSkipped('Created payment is not in cancellable state');
        }

        $cancelled = $gateway->cancelPayment($created->getPaymentId());

        expect($cancelled->getStatus())->toBe(PaymentStatus::CANCELLED);
    }

    public function test_refund_payment_returns_refunded_status(): void
    {
        if ($this->shouldSkipApiTests()) {
            $this->markTestSkipped('Skipping API test');
        }

        $gateway = $this->getGateway();

        if (! $gateway->supports('refunds')) {
            $this->markTestSkipped('Gateway does not support refunds');
        }

        // This test requires a paid payment, which typically requires
        // completing a payment flow. Implementations should override
        // this test with their specific flow.
        $this->markTestSkipped('Override this test with gateway-specific payment completion flow');
    }

    public function test_partial_refund_returns_partially_refunded_status(): void
    {
        if ($this->shouldSkipApiTests()) {
            $this->markTestSkipped('Skipping API test');
        }

        $gateway = $this->getGateway();

        if (! $gateway->supports('partial_refunds')) {
            $this->markTestSkipped('Gateway does not support partial refunds');
        }

        // Similar to full refund, requires completed payment
        $this->markTestSkipped('Override this test with gateway-specific payment completion flow');
    }

    public function test_get_payment_methods_returns_array(): void
    {
        if ($this->shouldSkipApiTests()) {
            $this->markTestSkipped('Skipping API test');
        }

        $gateway = $this->getGateway();

        $methods = $gateway->getPaymentMethods();

        expect($methods)->toBeArray();
    }
}
