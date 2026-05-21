<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Middleware;

use AIArmada\CommerceSupport\Events\OwnerNotResolvedForRequestEvent;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class NeedsOwner
{
    /**
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (OwnerContext::resolve() === null) {
            event(new OwnerNotResolvedForRequestEvent($request));

            throw NoCurrentOwnerException::forRequest($request);
        }

        return $next($request);
    }
}
