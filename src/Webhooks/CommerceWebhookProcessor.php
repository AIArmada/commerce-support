<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Webhooks;

use AIArmada\CommerceSupport\Actions\ProcessWebhookCallAction;
use Illuminate\Database\Eloquent\Builder;
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
        ProcessWebhookCallAction::run(
            webhookCall: $this->webhookCall,
            extractEventType: fn (array $payload): string => $this->extractEventType($payload),
            isDuplicateProcessedEvent: fn (WebhookCall $current, array $payload, string $eventType): bool => $this->isDuplicateProcessedEvent($current, $payload, $eventType),
            processEvent: function (string $eventType, array $payload): void {
                $this->processEvent($eventType, $payload);
            },
        );
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
        $eventType = $payload['event_type'] ?? null;

        return is_string($eventType) && $eventType !== '' ? $eventType : 'unknown';
    }

    /**
     * Extract the provider event id from the canonical payload shape.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventId(array $payload): ?string
    {
        $candidates = [
            $payload['event_id'] ?? null,
            $payload['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * Determine if this webhook event was already processed in a different webhook row.
     *
     * Deduplication requires both the canonical provider id and event type to match.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function isDuplicateProcessedEvent(WebhookCall $current, array $payload, string $eventType): bool
    {
        $eventId = $this->extractEventId($payload);

        if ($eventId === null) {
            return false;
        }

        return WebhookCall::query()
            ->where('name', $current->name)
            ->whereKeyNot($current->getKey())
            ->whereNotNull('processed_at')
            ->where(function (Builder $builder) use ($eventId): void {
                $builder->where('payload->event_id', $eventId)
                    ->orWhere('payload->id', $eventId);
            })
            ->where('payload->event_type', $eventType)
            ->exists();
    }

    /**
     * Get the webhook call model.
     */
    protected function getWebhookCall(): WebhookCall
    {
        return $this->webhookCall;
    }
}
