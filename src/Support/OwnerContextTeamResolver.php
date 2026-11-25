<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Maps Spatie team resolution onto the commerce owner context.
 *
 * HTTP contexts store the team owner on the request-scoped owner context.
 * CLI/queue contexts have no request bag: a team switch applies to the active
 * `OwnerContext::withOwner(...)` scope only, because persistent process-wide
 * owner state would leak across queued jobs.
 */
final class OwnerContextTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int | string | null
    {
        return OwnerContext::resolve()?->getKey();
    }

    /**
     * @param  int|string|Model|null  $id
     */
    public function setPermissionsTeamId($id): void
    {
        $owner = $this->resolveOwner($id);

        if (OwnerContext::hasActiveRequest()) {
            OwnerContext::setForRequest($owner);

            return;
        }

        // CLI/queue: there is no request bag for persistent owner state, and
        // writing process-wide state would leak across queued jobs. Apply the
        // change to the active scoped override only; scopes are established
        // with OwnerContext::withOwner().
        OwnerContext::updateActiveOverride($owner);
    }

    private function resolveOwner(int | string | Model | null $id): ?Model
    {
        if ($id instanceof Model || $id === null) {
            return $id;
        }

        $teamType = config('commerce-support.owner.team_type');

        if (! is_string($teamType) || $teamType === '') {
            throw new InvalidArgumentException('commerce-support.owner.team_type must be configured to resolve a team model.');
        }

        return OwnerContext::fromTypeAndId($teamType, $id);
    }
}
