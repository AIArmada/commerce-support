<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\UserPreferences\Actions;

use AIArmada\CommerceSupport\Features\UserPreferences\Models\SavedSearch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class CreateSavedSearch
{
    /** @param array<string,mixed> $content */
    public function execute(Model & Authenticatable $user, array $content, ?Model $searchable = null): SavedSearch
    {
        if (! $user->exists || ($searchable !== null && ! $searchable->exists)) {
            throw new InvalidArgumentException('Saved search identities must be persisted.');
        }

        $search = new SavedSearch($content);
        $search->user()->associate($user);

        if ($searchable !== null) {
            $search->searchable()->associate($searchable);
        }

        $search->saveOrFail();

        return $search;
    }
}
