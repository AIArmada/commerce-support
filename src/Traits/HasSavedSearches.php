<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use AIArmada\CommerceSupport\Models\SavedSearch;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSavedSearches
{
    public function savedSearches(): MorphMany
    {
        return $this->morphMany(SavedSearch::class, 'user');
    }
}
