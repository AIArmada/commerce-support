<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotificationPreferences
{
    public function notificationPreferences(): MorphMany
    {
        return $this->morphMany(NotificationPreference::class, 'user');
    }
}
