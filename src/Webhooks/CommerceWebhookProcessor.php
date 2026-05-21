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
        return $payload['event_type']
            ?? $payload['event']
            ?? $payload['type']
            ?? 'unknown';
    }

    /**
     * Extract provider event id from common payload shapes.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventId(array $payload): ?string
    {
        $candidates = [
            $payload['event_id'] ?? null,
            $payload['eventId'] ?? null,
            $payload['id'] ?? null,
            is_array($payload['data'] ?? null) ? ($payload['data']['id'] ?? null) : null,
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
     * Deduplication logic:
     * - If the current payload contains an explicit event type (event_type|event|type), candidate
     *   rows must match that exact type in at least one of those fields.
     * - If the current payload has no event type in any field, only rows that also have no event
     *   type in any field are considered duplicates, preventing cross-type false positives when
     *   providers reuse IDs across event families.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function isDuplicateProcessedEvent(WebhookCall $current, array $payload, string $eventType): bool
    {
        $eventId = $this->extractEventId($payload);

        if ($eventId === null) {
            return false;
        }

        $hasExplicitType = array_key_exists('event_type', $payload)
            || array_key_exists('event', $payload)
            || array_key_exists('type', $payload);

        return WebhookCall::query()
            ->where('name', $current->name)
            ->whereKeyNot($current->getKey())
            ->whereNotNull('processed_at')
            ->where(function (Builder $builder) use ($eventId): void {
                $builder->where('payload->event_id', $eventId)
                    ->orWhere('payload->eventId', $eventId)
                    ->orWhere('payload->id', $eventId)
                    ->orWhere('payload->data->id', $eventId);
            })
            ->where(function (Builder $builder) use ($eventType, $hasExplicitType): void {
                if ($hasExplicitType) {
                    // Current row has an explicit type: candidate must carry the same type.
                    $builder->where('payload->event_type', $eventType)
                        ->orWhere('payload->event', $eventType)
                        ->orWhere('payload->type', $eventType);
                } else {
                    // Current row has no type at all: only match rows that also have no type,
                    // so we never conflate type-bearing and type-less events with the same ID.
                    $builder->whereNull('payload->event_type')
                        ->whereNull('payload->event')
                        ->whereNull('payload->type');
                }
            })
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
