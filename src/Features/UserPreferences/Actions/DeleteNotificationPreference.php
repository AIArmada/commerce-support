<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\UserPreferences\Actions;

use AIArmada\CommerceSupport\Features\UserPreferences\Models\NotificationPreference;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class DeleteNotificationPreference
{
    public function execute(Model & Authenticatable $user, string $id): bool
    {
        if (! $user->exists) {
            throw new InvalidArgumentException('User must be persisted.');
        }

        return NotificationPreference::query()->whereMorphedTo('user', $user)->whereKey($id)->firstOrFail()->delete() === true;
    }
}
