<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;
use Akaunting\Money\Money;

/**
 * Universal payment gateway interface.
 *
 * This interface provides a standard contract for all payment gateways,
 * allowing easy swapping between providers (Stripe, CHIP, PayPal, SenangPay, eGHL, etc.)
 * without changing application code.
 *
 * @example
 * ```php
 * // In your checkout controller
 * public function checkout(PaymentGatewayInterface $gateway)
 * {
 *     $payment = $gateway->createPayment($cart, $customer, [
 *         'success_url' => route('payment.success'),
 *         'failure_url' => route('payment.failed'),
 *     ]);
 *
 *     return redirect($payment->getCheckoutUrl());
 * }
 * ```
 */
interface PaymentGatewayInterface
{
    /**
     * Get the gateway identifier (e.g., 'chip', 'stripe', 'paypal').
     */
    public function getName(): string;

    /**
     * Get the display name for the gateway.
     */
    public function getDisplayName(): string;

    /**
     * Check if the gateway is in test/sandbox mode.
     */
    public function isTestMode(): bool;

    /**
     * Create a payment intent from a checkoutable object.
     *
     * @param  CheckoutableInterface  $checkoutable  Cart, Order, or Invoice to charge
     * @param  CustomerInterface|null  $customer  Customer details for the payment
     * @param  array<string, mixed>  $options  Gateway-specific options
     *
     * Options may include:
     * - 'success_url': URL to redirect after successful payment
     * - 'failure_url': URL to redirect after failed payment
     * - 'cancel_url': URL to redirect if customer cancels
     * - 'webhook_url': URL for payment status callbacks
     * - 'send_receipt': Whether to send receipt email
     * - 'metadata': Additional metadata to attach
     *
     * @throws PaymentGatewayException If payment creation fails
     */
    public function createPayment(
        CheckoutableInterface $checkoutable,
        ?CustomerInterface $customer = null,
        array $options = []
    ): PaymentIntentInterface;

    /**
     * Retrieve an existing payment by ID.
     *
     * @throws PaymentGatewayException If payment not found or retrieval fails
     */
    public function getPayment(string $paymentId): PaymentIntentInterface;

    /**
     * Cancel a pending payment.
     *
     * @throws PaymentGatewayException If cancellation fails
     */
    public function cancelPayment(string $paymentId): PaymentIntentInterface;

    /**
     * Refund a payment (full or partial).
     *
     * @param  Money|null  $amount  Amount to refund, null for full refund
     *
     * @throws PaymentGatewayException If refund fails
     */
    public function refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;

    /**
     * Capture an authorized payment.
     *
     * For gateways that support pre-authorization flows.
     *
     * @param  Money|null  $amount  Amount to capture, null for full amount
     *
     * @throws PaymentGatewayException If capture fails
     */
    public function capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;

    /**
     * Get available payment methods for the gateway.
     *
     * @param  array<string, mixed>  $filters  Filter criteria (currency, country, etc.)
     * @return array<string, mixed>
     */
    public function getPaymentMethods(array $filters = []): array;

    /**
     * Check if the gateway supports a specific feature.
     *
     * Features may include:
     * - 'refunds': Supports refunds
     * - 'partial_refunds': Supports partial refunds
     * - 'pre_authorization': Supports auth + capture flow
     * - 'recurring': Supports recurring payments
     * - 'webhooks': Supports webhook notifications
     * - 'hosted_checkout': Supports redirect-based checkout
     * - 'embedded_checkout': Supports embedded payment forms
     */
    public function supports(string $feature): bool;

    /**
     * Get the webhook handler for this gateway.
     */
    public function getWebhookHandler(): WebhookHandlerInterface;
}
