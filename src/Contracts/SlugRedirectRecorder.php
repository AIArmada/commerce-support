<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Record a redirect from a model's previous slug to its current one.
 *
 * Storage is host-defined (redirect table, cache, edge config): packages
 * depend on this contract, hosts provide the implementation.
 */
interface SlugRedirectRecorder
{
    /**
     * @return bool Whether a redirect was recorded.
     */
    public function record(Model $model, ?string $previousSlug): bool;
}
