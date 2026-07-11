<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use AIArmada\CommerceSupport\Models\AuthzScope;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\PermissionRegistrar;

final class DeleteAuthzScopeAction
{
    use AsAction;

    public function handle(AuthzScope $authzScope): void
    {
        DB::transaction(function () use ($authzScope): void {
            $authzScope->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
