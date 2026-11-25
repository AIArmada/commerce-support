<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\Reporting\Actions;

use AIArmada\CommerceSupport\Features\Reporting\Api\ReportStatus;
use AIArmada\CommerceSupport\Features\Reporting\Models\Report;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class TransitionReport
{
    public function __construct(private Gate $gate) {}

    public function execute(string $id, ReportStatus $status, Model & Authenticatable $reviewer, ?string $resolution = null): bool
    {
        if (! $reviewer->exists) {
            throw new InvalidArgumentException('The reviewer must be persisted.');
        }

        return (new Report)->getConnection()->transaction(function () use ($id, $status, $reviewer, $resolution): bool {
            $report = Report::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->gate->forUser($reviewer)->authorize('commerce-support.review-report', $report);

            if ($report->status === $status) {
                return true;
            }

            $allowed = match ($report->status) {
                ReportStatus::Open => [ReportStatus::UnderReview, ReportStatus::Resolved, ReportStatus::Rejected, ReportStatus::Archived],
                ReportStatus::UnderReview => [ReportStatus::Resolved, ReportStatus::Rejected, ReportStatus::Archived],
                ReportStatus::Resolved, ReportStatus::Rejected => [ReportStatus::Archived],
                ReportStatus::Archived => [],
            };

            if (! in_array($status, $allowed, true)) {
                throw new DomainException('Invalid report status transition.');
            }

            $now = CarbonImmutable::now();
            $report->status = $status;

            if ($status === ReportStatus::Archived) {
                $report->archived_at = $now;
            } else {
                $report->reviewedBy()->associate($reviewer);
                $report->reviewed_at ??= $now;

                if ($status === ReportStatus::Resolved) {
                    $report->resolved_at = $now;
                    $report->resolution = $resolution;
                } elseif ($status === ReportStatus::Rejected) {
                    $report->rejected_at = $now;
                    $report->resolution = $resolution;
                }
            }

            return $report->save();
        });
    }
}
