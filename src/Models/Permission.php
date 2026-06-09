<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission model extending Spatie Permission with UUID support.
 *
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Role> $roles
 */
final class Permission extends SpatiePermission
{
    use HasUuids;

    /**
     * @return Collection<int, static>
     */
    protected static function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        return parent::getPermissions($params, $onlyOne);
    }

    /**
     * @throws PermissionAlreadyExists
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);

        $permission = static::getPermission(['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']]);

        if ($permission) {
            throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    public static function findOrCreate(string $name, ?string $guardName = null): PermissionContract
    {
        $guardName ??= Guard::getDefaultName(static::class);
        $permission = static::getPermission(['name' => $name, 'guard_name' => $guardName]);

        if (! $permission) {
            $attributes = ['name' => $name, 'guard_name' => $guardName];

            return static::query()->create($attributes);
        }

        return $permission;
    }

    public function getTable(): string
    {
        $table = config('permission.table_names.permissions');

        if (is_string($table) && $table !== '') {
            return $table;
        }

        return parent::getTable();
    }
}
