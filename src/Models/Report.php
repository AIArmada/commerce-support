<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use AIArmada\CommerceSupport\Enums\ReportSeverity;
use AIArmada\CommerceSupport\Enums\ReportStatus;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $reportable_type
 * @property string $reportable_id
 * @property string|null $reporter_type
 * @property string|null $reporter_id
 * @property string $report_type
 * @property ReportStatus $status
 * @property ReportSeverity $severity
 * @property string|null $title
 * @property string|null $message
 * @property string|null $reviewed_by_type
 * @property string|null $reviewed_by_id
 * @property CarbonImmutable|null $reported_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $resolution
 * @property string|null $internal_notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $reportable
 * @property-read Model|Eloquent|null $reporter
 * @property-read Model|Eloquent|null $reviewedBy
 */
class Report extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Only reporter-supplied content is mass-assignable. Polymorphic
     * identities (reportable/reporter/reviewed_by), workflow state
     * (status/severity/resolution/internal_notes) and lifecycle timestamps
     * must be set server-side via relationships or explicit transitions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_type',
        'title', 'message',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'open',
        'severity' => 'medium',
    ];

    public function getTable(): string
    {
        return config('commerce-support.database.tables.reports', 'reports');
    }

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'severity' => ReportSeverity::class,
            'reported_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewedBy(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::creating(function (Report $report): void {
            $report->reported_at ??= CarbonImmutable::now();
        });
    }

    public function startReview(Model $reviewer): bool
    {
        $this->reviewedBy()->associate($reviewer);
        $this->status = ReportStatus::UnderReview;
        $this->reviewed_at = CarbonImmutable::now();

        return $this->save();
    }

    public function resolve(Model $reviewer, ?string $resolution = null): bool
    {
        $this->reviewedBy()->associate($reviewer);
        $this->status = ReportStatus::Resolved;
        $this->reviewed_at ??= CarbonImmutable::now();
        $this->resolved_at = CarbonImmutable::now();

        if ($resolution !== null) {
            $this->resolution = $resolution;
        }

        return $this->save();
    }

    public function reject(Model $reviewer, ?string $resolution = null): bool
    {
        $this->reviewedBy()->associate($reviewer);
        $this->status = ReportStatus::Rejected;
        $this->reviewed_at ??= CarbonImmutable::now();
        $this->rejected_at = CarbonImmutable::now();

        if ($resolution !== null) {
            $this->resolution = $resolution;
        }

        return $this->save();
    }

    public function archive(): bool
    {
        $this->status = ReportStatus::Archived;
        $this->archived_at = CarbonImmutable::now();

        return $this->save();
    }
}
