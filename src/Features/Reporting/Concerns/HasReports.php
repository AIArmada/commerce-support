<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\Reporting\Concerns;

use AIArmada\CommerceSupport\Features\Reporting\Models\Report;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasReports
{
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
