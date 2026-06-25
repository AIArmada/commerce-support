<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

final class FilamentPermission
{
    public static function hasAbility(string $ability): bool
    {
        $user = self::resolveUser();

        if (! $user instanceof Authorizable) {
            return false;
        }

        if (self::isSuperAdmin($user)) {
            return true;
        }

        return $user->can($ability);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public static function hasAnyAbility(array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if (self::hasAbility($ability)) {
                return true;
            }
        }

        return false;
    }

    private static function resolveUser(): mixed
    {
        return Filament::auth()?->user() ?? Auth::user();
    }

    private static function isSuperAdmin(Authorizable $user): bool
    {
        $superAdminRole = config('authz.super_admin_role');

        if (! is_string($superAdminRole) || $superAdminRole === '' || ! method_exists($user, 'hasRole')) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $teams = $registrar->teams;
        $registrar->teams = false;

        try {
            /** @var bool $hasRole */
            $hasRole = call_user_func([$user, 'hasRole'], $superAdminRole);

            return $hasRole;
        } finally {
            $registrar->teams = $teams;
        }
    }
}
