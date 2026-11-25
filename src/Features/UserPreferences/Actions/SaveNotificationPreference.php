<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\UserPreferences\Actions;

use AIArmada\CommerceSupport\Features\UserPreferences\Models\NotificationPreference;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class SaveNotificationPreference
{
    /** @param array<string,mixed> $content */
    public function execute(Model & Authenticatable $user, array $content, ?string $id = null): NotificationPreference
    {
        if (! $user->exists) {
            throw new InvalidArgumentException('The user must be persisted.');
        }

        $preference = $id === null
            ? new NotificationPreference
            : NotificationPreference::query()->whereMorphedTo('user', $user)->whereKey($id)->firstOrFail();

        $preference->fill($content);
        $preference->user()->associate($user);
        $preference->saveOrFail();

        return $preference;
    }
}
