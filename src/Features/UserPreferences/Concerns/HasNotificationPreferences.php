<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\UserPreferences\Concerns;

use AIArmada\CommerceSupport\Features\UserPreferences\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotificationPreferences
{
    public function notificationPreferences(): MorphMany
    {
        return $this->morphMany(NotificationPreference::class, 'user');
    }
}
