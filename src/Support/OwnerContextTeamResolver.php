<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

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
        if ($id instanceof Model || $id === null) {
            OwnerContext::setForRequest($id);

            return;
        }

        $teamType = config('commerce-support.owner.team_type');

        if (! is_string($teamType) || $teamType === '') {
            throw new InvalidArgumentException('commerce-support.owner.team_type must be configured to resolve a team model.');
        }

        $owner = OwnerContext::fromTypeAndId($teamType, $id);

        OwnerContext::setForRequest($owner);
    }
}
