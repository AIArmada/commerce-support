<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Events;

use Illuminate\Database\Eloquent\Model;

final class ForgettingCurrentOwnerEvent
{
    public function __construct(public Model $owner) {}
}
