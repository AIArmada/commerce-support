<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Events;

use DateTimeImmutable;

/**
 * Base interface for all commerce-related events.
 *
 * This interface provides a common contract for events across all commerce packages,
 * enabling consistent event handling, logging, and event sourcing capabilities.
 */
interface CommerceEventInterface
{
    /**
     * Get a unique identifier for this event occurrence.
     *
     * @return string UUID for this specific event instance
     */
    public function getEventId(): string;

    /**
     * Get the event type identifier.
     *
     * This should return a dot-notation string identifying the event type,
     * e.g., 'cart.item.added', 'order.completed', 'payment.processed'.
     *
     * @return string Event type identifier
     */
    public function getEventType(): string;

    /**
     * Get the timestamp when the event occurred.
     *
     * @return DateTimeImmutable When the event was created
     */
    public function getOccurredAt(): DateTimeImmutable;

    /**
     * Get the event payload as an array.
     *
     * This should contain all relevant data about the event for
     * persistence, replay, and analysis purposes.
     *
     * @return array<string, mixed> Event payload data
     */
    public function toEventPayload(): array;

    /**
     * Get event metadata for logging and tracing.
     *
     * @return array<string, mixed> Metadata like correlation_id, causation_id, user_agent, etc.
     */
    public function getEventMetadata(): array;
}
