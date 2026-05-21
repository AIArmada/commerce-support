<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

final class NoCurrentOwnerException extends RuntimeException
{
    public static function forRequest(Request $request): self
    {
        return new self(sprintf('No current owner could be resolved for request path [%s].', $request->path()));
    }
}
