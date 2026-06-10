<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Trait for webhook call models to manage processing lifecycle.
 *
 * Provides a consistent interface for tracking webhook call state through
 * its lifecycle: pending → processed / failed (with optional retries).
 *
 * @property string|null $status
 * @property string|null $exception
 * @property int $retry_count
 *
 * @example
 * ```php
 * class Webhook extends Model
 * {
 *     use HasWebhookLifecycle;
 * }
 *
 * $webhook->markProcessed(12.5);
 * $webhook->markFailed($exception);
 * $webhook->markForRetry('Network timeout');
 * ```
 */
trait HasWebhookLifecycle
{
    /**
     * Mark the webhook call as successfully processed.
     *
     * @param  float  $processingTimeMs  Optional processing duration in milliseconds
     */
    public function markProcessed(float $processingTimeMs = 0): static
    {
        $this->setAttribute('status', 'processed');
        $this->setAttribute('processed_at', new CarbonImmutable);

        if ($processingTimeMs > 0) {
            $this->setAttribute('processing_time_ms', $processingTimeMs);
        }

        $this->save();

        return $this;
    }

    /**
     * Mark the webhook call as permanently failed.
     */
    public function markFailed(Throwable $exception): static
    {
        $this->setAttribute('status', 'failed');
        $this->setAttribute('failed_at', new CarbonImmutable);
        $this->setAttribute('exception', (string) $exception);

        $this->save();

        return $this;
    }

    /**
     * Mark the webhook call for retry (non-terminal failure).
     */
    public function markForRetry(string $reason): static
    {
        $this->setAttribute('status', 'retrying');
        $this->setAttribute('last_retry_at', new CarbonImmutable);
        $this->increment('retry_count');

        $this->save();

        return $this;
    }

    /**
     * Check if the webhook call is pending processing.
     */
    public function isPending(): bool
    {
        $status = $this->getAttribute('status');

        return $status === 'pending' || $status === null;
    }

    /**
     * Check if the webhook call has been successfully processed.
     */
    public function isProcessed(): bool
    {
        return $this->getAttribute('status') === 'processed';
    }

    /**
     * Check if the webhook call has permanently failed.
     */
    public function isFailed(): bool
    {
        return $this->getAttribute('status') === 'failed';
    }
}
