<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Models;

use Carbon\CarbonImmutable;
use Eloquent;
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
 * @property string $status
 * @property string $severity
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
final class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reportable_type', 'reportable_id',
        'reporter_type', 'reporter_id',
        'report_type', 'status', 'severity',
        'title', 'message',
        'reviewed_by_type', 'reviewed_by_id',
        'reported_at', 'reviewed_at', 'resolved_at', 'rejected_at', 'archived_at',
        'resolution', 'internal_notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('commerce-support.database.tables.reports', 'reports');
    }

    protected function casts(): array
    {
        return [
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
}
