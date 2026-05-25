<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Middleware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class SetExplicitGlobalOwnerContext extends OwnerIdentificationMiddleware
{
    protected function resolveOwnerFromRequest(Request $request): ?Model
    {
        return null;
    }
}
