<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under Review',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }
}
