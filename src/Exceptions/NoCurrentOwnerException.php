<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Exceptions;

use Illuminate\Http\Request;

final class NoCurrentOwnerException extends CommerceException
{
    public static function forRequest(Request $request): self
    {
        return new self(sprintf('No current owner could be resolved for request path [%s].', $request->path()));
    }
}
