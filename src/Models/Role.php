<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role model extending Spatie Permission with UUID support.
 *
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 */
final class Role extends SpatieRole
{
    use HasUuids;

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        $pivotTable = (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $rolePivotKey = (string) config('permission.column_names.role_pivot_key', 'role_id');
        $permissionPivotKey = (string) config('permission.column_names.permission_pivot_key', 'permission_id');

        /** @var BelongsToMany<Permission, $this> $relation */
        $relation = $this->belongsToMany(Permission::class, $pivotTable, $rolePivotKey, $permissionPivotKey);

        return $relation;
    }

    public function authzScope(): BelongsTo
    {
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        return $this->belongsTo(AuthzScope::class, $teamsKey);
    }

    public function getTable(): string
    {
        $table = config('permission.table_names.roles');

        if (is_string($table) && $table !== '') {
            return $table;
        }

        return parent::getTable();
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);

        $registrar = app(PermissionRegistrar::class);

        if ($registrar->teams && config('filament-authz.scoped_to_tenant', true)) {
            $teamsKey = $registrar->teamsKey;
            $teamId = getPermissionsTeamId();

            if ($teamId !== null && ! array_key_exists($teamsKey, $attributes)) {
                $attributes[$teamsKey] = $teamId;
            }
        }

        return static::query()->create($attributes);
    }

    /**
     * @return RoleContract|Role|null
     */
    protected static function findByParam(array $params = []): ?RoleContract
    {
        $query = static::query();

        $registrar = app(PermissionRegistrar::class);

        if ($registrar->teams) {
            $teamsKey = $registrar->teamsKey;
            $teamId = $params[$teamsKey] ?? getPermissionsTeamId();

            if (config('filament-authz.scoped_to_tenant', true)) {
                if ($teamId === null) {
                    $query->whereNull($teamsKey);
                } else {
                    $query->where($teamsKey, $teamId);
                }
            } else {
                $query->where(fn ($q) => $q->whereNull($teamsKey)
                    ->orWhere($teamsKey, $teamId));
            }

            unset($params[$teamsKey]);
        }

        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }
}
