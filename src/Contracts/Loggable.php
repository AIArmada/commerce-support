<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use Spatie\Activitylog\LogOptions;

/**
 * Interface for models that support activity logging.
 */
interface Loggable
{
    /**
     * Get the activity log options for this model.
     */
    public function getActivitylogOptions(): LogOptions;

    /**
     * Get a description of the activity for display.
     */
    public function getDescriptionForEvent(string $eventName): string;
}
