<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Models\AuthzScope;
use AIArmada\CommerceSupport\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\PermissionRegistrar;

final class MoveRoleToScopeAction
{
    use AsAction;

    public function handle(string $roleId, ?string $targetScopeId): void
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams) {
            throw new AuthorizationException('Teams/scopes are not enabled on this installation.');
        }

        $role = Role::query()->findOrFail($roleId);
        $teamsKey = (string) $registrar->teamsKey;
        $previousScopeId = $role->getAttribute($teamsKey);
        $previousScopeId = is_scalar($previousScopeId) ? (string) $previousScopeId : null;

        if ($previousScopeId === $targetScopeId) {
            return;
        }

        if (filled($targetScopeId)) {
            AuthzScope::query()->findOrFail($targetScopeId);
        }

        $table = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $rolePivotKey = (string) config('permission.column_names.role_pivot_key', 'role_id');

        DB::transaction(function () use ($role, $teamsKey, $targetScopeId, $previousScopeId, $table, $rolePivotKey): void {
            $role->update([$teamsKey => $targetScopeId]);

            DB::table($table)
                ->where($teamsKey, $previousScopeId)
                ->where($rolePivotKey, $role->getKey())
                ->update([$teamsKey => $targetScopeId]);
        });

        $registrar->forgetCachedPermissions();
    }
}
