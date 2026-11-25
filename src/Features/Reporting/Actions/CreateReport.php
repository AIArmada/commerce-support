<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\Reporting\Actions;

use AIArmada\CommerceSupport\Features\Reporting\Models\Report;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class CreateReport
{
    /** @param array<string,mixed> $content */
    public function execute(Model $reportable, (Model & Authenticatable) | null $reporter, array $content): Report
    {
        if (! $reportable->exists || ($reporter !== null && ! $reporter->exists)) {
            throw new InvalidArgumentException('Report identities must be persisted.');
        }

        $report = new Report($content);
        $report->reportable()->associate($reportable);

        if ($reporter !== null) {
            $report->reporter()->associate($reporter);
        }

        $report->saveOrFail();

        return $report;
    }
}
