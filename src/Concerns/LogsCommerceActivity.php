<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Shared trait for logging commerce activity across all packages.
 *
 * This trait provides a standardized way to log model changes using
 * spatie/laravel-activitylog with consistent defaults for commerce operations.
 *
 * @example
 * ```php
 * use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
 *
 * class Order extends Model
 * {
 *     use LogsCommerceActivity;
 *
 *     protected function getLoggableAttributes(): array
 *     {
 *         return ['status', 'total', 'customer_id'];
 *     }
 *
 *     protected function getActivityLogName(): string
 *     {
 *         return 'orders';
 *     }
 * }
 * ```
 */
trait LogsCommerceActivity // @phpstan-ignore trait.unused
{
    use LogsActivity;

    /**
     * Configure activity logging options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getLoggableAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getActivityLogName());
    }

    /**
     * Get a description of the activity for display.
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $modelName = class_basename($this);

        return match ($eventName) {
            'created' => "{$modelName} was created",
            'updated' => "{$modelName} was updated",
            'deleted' => "{$modelName} was deleted",
            default => "{$modelName} {$eventName}",
        };
    }

    /**
     * Get the attributes that should be logged.
     *
     * Override this method to specify which attributes to track.
     *
     * @return array<int, string>
     */
    protected function getLoggableAttributes(): array
    {
        return $this->fillable ?? [];
    }

    /**
     * Get the activity log name for this model.
     *
     * Override this method to categorize logs by domain.
     */
    protected function getActivityLogName(): string
    {
        return 'commerce';
    }
}
