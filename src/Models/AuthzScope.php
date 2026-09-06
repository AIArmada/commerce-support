<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property string $id
 * @property string $scopeable_type
 * @property string $scopeable_id
 * @property string|null $label
 */
final class AuthzScope extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scopeable_type',
        'scopeable_id',
        'label',
    ];

    public function getTable(): string
    {
        $tables = config('authz.database.tables', []);
        $prefix = config('authz.database.table_prefix', '');

        return $prefix . ($tables['scopes'] ?? 'authz_scopes');
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::deleting(function (AuthzScope $authzScope): void {
            $registrar = app(PermissionRegistrar::class);

            if (! $registrar->teams) {
                return;
            }

            $teamsKey = (string) $registrar->teamsKey;
            $roleClass = config('permission.models.role', Role::class);
            $tableNames = (array) config('permission.table_names', []);
            $columnNames = (array) config('permission.column_names', []);

            /** @var class-string<Model> $roleClass */
            $role = new $roleClass;
            $roleTable = $role->getTable();
            $roleKey = $role->getKeyName();
            $rolePivotKey = (string) ($columnNames['role_pivot_key'] ?? 'role_id');

            DB::transaction(function () use ($authzScope, $teamsKey, $tableNames, $roleTable, $roleKey, $rolePivotKey): void {
                $roleIds = DB::table($roleTable)
                    ->where($teamsKey, $authzScope->getKey())
                    ->pluck($roleKey)
                    ->all();

                DB::table((string) ($tableNames['model_has_roles'] ?? 'model_has_roles'))
                    ->where($teamsKey, $authzScope->getKey())
                    ->delete();

                DB::table((string) ($tableNames['model_has_permissions'] ?? 'model_has_permissions'))
                    ->where($teamsKey, $authzScope->getKey())
                    ->delete();

                if ($roleIds === []) {
                    return;
                }

                DB::table((string) ($tableNames['model_has_roles'] ?? 'model_has_roles'))
                    ->whereIn($rolePivotKey, $roleIds)
                    ->delete();

                DB::table((string) ($tableNames['role_has_permissions'] ?? 'role_has_permissions'))
                    ->whereIn($rolePivotKey, $roleIds)
                    ->delete();

                DB::table($roleTable)
                    ->whereIn($roleKey, $roleIds)
                    ->delete();
            });

            $registrar->forgetCachedPermissions();
        });
    }
}
