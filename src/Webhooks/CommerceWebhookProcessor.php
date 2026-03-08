<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Webhooks;

use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;

/**
 * Base webhook processor for commerce packages.
 *
 * Extend this class to implement package-specific webhook processing.
 *
 * @property WebhookCall $webhookCall
 *
 * @example
 * ```php
 * class ProcessChipWebhook extends CommerceWebhookProcessor
 * {
 *     protected function processEvent(string $eventType, array $payload): void
 *     {
 *         match($eventType) {
 *             'purchase.paid' => $this->handlePurchasePaid($payload),
 *             default => null,
 *         };
 *     }
 * }
 * ```
 */
abstract class CommerceWebhookProcessor extends ProcessWebhookJob
{
    /**
     * Process the webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function processEvent(string $eventType, array $payload): void;

    /**
     * Process the webhook.
     */
    final public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        $eventType = $this->extractEventType($payload);

        $this->processEvent($eventType, $payload);

        // Mark as processed
        $this->webhookCall->update([
            'processed_at' => now(),
        ]);
    }

    /**
     * Extract the event type from the payload.
     *
     * Override this for different payload structures.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventType(array $payload): string
    {
        return $payload['event_type']
            ?? $payload['event']
            ?? $payload['type']
            ?? 'unknown';
    }

    /**
     * Get the webhook call model.
     */
    protected function getWebhookCall(): WebhookCall
    {
        return $this->webhookCall;
    }
}
