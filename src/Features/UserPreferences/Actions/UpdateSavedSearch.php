<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\UserPreferences\Actions;

use AIArmada\CommerceSupport\Features\UserPreferences\Models\SavedSearch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class UpdateSavedSearch
{
    /** @param array<string,mixed> $content */
    public function execute(Model & Authenticatable $user, string $id, array $content): SavedSearch
    {
        if (! $user->exists) {
            throw new InvalidArgumentException('User must be persisted.');
        }

        $search = SavedSearch::query()->whereMorphedTo('user', $user)->whereKey($id)->firstOrFail();
        $search->fill($content)->saveOrFail();

        return $search;
    }
}
