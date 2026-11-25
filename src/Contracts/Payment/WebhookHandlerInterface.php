<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Payment;

use AIArmada\CommerceSupport\Data\WebhookRequestData;
use RuntimeException;

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
     *
     * @throws RuntimeException If verification fails
     */
    public function verifyWebhook(WebhookRequestData $request): bool;

    /**
     * Parse the webhook payload and return a standardized result.
     */
    public function parseWebhook(WebhookRequestData $request): WebhookPayload;

    /**
     * Get the event type from the webhook.
     */
    public function getEventType(WebhookRequestData $request): string;

    /**
     * Check if the webhook is for a payment event.
     */
    public function isPaymentEvent(WebhookRequestData $request): bool;

    /**
     * Get the payment intent from the webhook if applicable.
     */
    public function getPaymentFromWebhook(WebhookRequestData $request): ?PaymentIntentInterface;
}
