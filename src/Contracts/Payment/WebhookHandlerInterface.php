<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;
use Illuminate\Http\Request;

/**
 * Interface for handling payment gateway webhooks.
 *
 * Each payment gateway sends webhooks differently, but this interface
 * provides a standard way to verify and parse webhook payloads.
 */
interface WebhookHandlerInterface
{
    /**
     * Verify the webhook signature/authenticity.
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @throws WebhookVerificationException If verification fails
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Parse the webhook payload and return a standardized result.
     *
     * @param  Request  $request  The incoming webhook request
     */
    public function parseWebhook(Request $request): WebhookPayload;

    /**
     * Get the event type from the webhook.
     */
    public function getEventType(Request $request): string;

    /**
     * Check if the webhook is for a payment event.
     */
    public function isPaymentEvent(Request $request): bool;

    /**
     * Get the payment intent from the webhook if applicable.
     */
    public function getPaymentFromWebhook(Request $request): ?PaymentIntentInterface;
}
