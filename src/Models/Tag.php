<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Tags\Tag as SpatieTag;

final class Tag extends SpatieTag
{
    use HasUuids;
}
