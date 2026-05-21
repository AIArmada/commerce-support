<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Exceptions;

use RuntimeException;
use Throwable;

final class TargetingRuleEvaluationException extends RuntimeException
{
    public static function forRule(string $type, string $reason, ?Throwable $previous = null): self
    {
        return new self(sprintf('Failed to evaluate targeting rule "%s": %s', $type, $reason), 0, $previous);
    }
}
