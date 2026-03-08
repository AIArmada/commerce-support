<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;

/**
 * Evaluates time window targeting rules.
 */
class TimeWindowEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::TimeWindow->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $startTime = $rule['start'] ?? $rule['start_time'] ?? null;
        $endTime = $rule['end'] ?? $rule['end_time'] ?? null;

        if ($startTime === null || $endTime === null) {
            return true;
        }

        $timezone = $rule['timezone'] ?? $context->getTimezone();
        $now = $context->getCurrentTime($timezone);
        $currentMinutes = ($now->hour * 60) + $now->minute;

        $startMinutes = $this->parseMinutes($startTime);
        $endMinutes = $this->parseMinutes($endTime);

        if ($startMinutes === null || $endMinutes === null) {
            return true;
        }

        if ($startMinutes <= $endMinutes) {
            return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
        }

        return $currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes;
    }

    public function getType(): string
    {
        return TargetingRuleType::TimeWindow->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        $startTime = $rule['start'] ?? $rule['start_time'] ?? null;
        $endTime = $rule['end'] ?? $rule['end_time'] ?? null;

        if ($startTime === null) {
            $errors[] = 'Start time is required';
        } elseif ($this->parseMinutes($startTime) === null) {
            $errors[] = 'Start time must be in HH:MM format';
        }

        if ($endTime === null) {
            $errors[] = 'End time is required';
        } elseif ($this->parseMinutes($endTime) === null) {
            $errors[] = 'End time must be in HH:MM format';
        }

        return $errors;
    }

    private function parseMinutes(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }
}
