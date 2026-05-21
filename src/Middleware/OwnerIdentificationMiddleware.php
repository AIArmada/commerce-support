<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Middleware;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Base middleware for tenant/owner identification.
 *
 * Subclasses implement `resolveOwnerFromRequest()` to extract the owner from domain,
 * header, auth context, or other request data. The middleware then sets the owner
 * context before request processing continues.
 *
 * This middleware should run early in the stack (before any tenant-scoped queries).
 *
 * @example
 * ```php
 * class IdentifyOwnerFromDomain extends OwnerIdentificationMiddleware
 * {
 *     protected function resolveOwnerFromRequest(Request $request): ?Model
 *     {
 *         $subdomain = explode('.', $request->getHost())[0];
 *
 *         if ($subdomain === 'app' || $subdomain === 'www') {
 *             return null; // Global/multi-tenant admin area
 *         }
 *
 *         $owner = Store::where('subdomain', $subdomain)->first();
 *
 *         if (! $owner) {
 *             throw new RuntimeException("Store '{$subdomain}' not found.");
 *         }
 *
 *         return $owner;
 *     }
 * }
 *
 * // In middleware stack (e.g., app/Http/Kernel.php)
 * protected $middleware = [
 *     IdentifyOwnerFromDomain::class,
 *     // ... other middleware
 * ];
 * ```
 */
abstract class OwnerIdentificationMiddleware
{
    /**
     * Handle the incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $owner = $this->resolveOwnerFromRequest($request);

        // Set owner context for the request lifecycle
        OwnerContext::setForRequest($owner);

        try {
            return $next($request);
        } finally {
            // No need to clean up; request attributes are discarded per-request
        }
    }

    /**
     * Resolve the owner from the incoming request.
     *
     * Implement this method to extract owner identification from domain, header,
     * auth context, or other request data.
     *
     * Return `null` for global/unauthenticated/multi-tenant contexts.
     *
     *
     * @throws RuntimeException if owner mode is enabled but owner cannot be resolved
     *                          and the route requires explicit owner context
     */
    abstract protected function resolveOwnerFromRequest(Request $request): ?Model;
}
