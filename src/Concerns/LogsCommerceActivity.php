<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

use AIArmada\CommerceSupport\Support\SensitiveAttributes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
            ->dontLogEmptyChanges()
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
     * Override this method with an explicit per-model allowlist to specify
     * which attributes to track. The default is the fillable list minus
     * sensitive attributes (credentials and PII), which are never logged
     * unless a model explicitly allowlists them.
     *
     * @return array<int, string>
     */
    protected function getLoggableAttributes(): array
    {
        return SensitiveAttributes::exclude($this->fillable);
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
