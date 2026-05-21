<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Events;

use Illuminate\Http\Request;

final class OwnerNotResolvedForRequestEvent
{
    public function __construct(public Request $request) {}
}
