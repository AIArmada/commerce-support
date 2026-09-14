<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Webhooks;

use AIArmada\CommerceSupport\Actions\ProcessWebhookCallAction;
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
            extractEventId: fn (array $payload): ?string => $this->extractEventId($payload),
            isDuplicateProcessedEvent: fn (WebhookCall $current, array $payload, string $eventType): bool => $this->isDuplicateProcessedEvent($current, $payload, $eventType),
            processEvent: function (string $eventType, array $payload): void {
                $this->processEvent($eventType, $payload);
            },
            extractOwner: fn (array $payload): array => $this->extractOwner($payload),
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
     * Extract the owner identity carried by the delivery, if any.
     *
     * Controllers that resolve an owner before storing the delivery stamp
     * `__owner_type` / `__owner_id` on the payload; the claim persists that
     * identity so deduplication stays scoped to the owning tenant.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: string|null, 1: string|null}
     */
    protected function extractOwner(array $payload): array
    {
        $type = $payload['__owner_type'] ?? null;
        $id = $payload['__owner_id'] ?? null;

        return [
            is_string($type) && $type !== '' ? $type : null,
            is_scalar($id) && (string) $id !== '' ? (string) $id : null,
        ];
    }

    /**
     * Determine if this webhook event was already processed in a different webhook row.
     *
     * Deduplication requires the canonical provider id, the event type, and
     * the owner identity to match, so two owners' identical provider events
     * never collapse into one delivery.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function isDuplicateProcessedEvent(WebhookCall $current, array $payload, string $eventType): bool
    {
        $eventId = $this->extractEventId($payload);

        if ($eventId === null) {
            return false;
        }

        $query = WebhookCall::query()
            ->where('name', $current->name)
            ->whereKeyNot($current->getKey())
            ->whereNotNull('processed_at')
            ->where('event_id', $eventId)
            ->where('event_type', $eventType);

        if (ProcessWebhookCallAction::supportsOwnerDedup()) {
            $ownerHash = $current->getAttribute('owner_hash');

            if (is_string($ownerHash) && $ownerHash !== '') {
                $query->where('owner_hash', $ownerHash);
            } else {
                $query->whereNull('owner_hash');
            }
        }

        return $query->exists();
    }

    /**
     * Get the webhook call model.
     */
    protected function getWebhookCall(): WebhookCall
    {
        return $this->webhookCall;
    }
}
