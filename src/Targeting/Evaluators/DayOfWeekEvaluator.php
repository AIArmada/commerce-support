<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;
use Carbon\CarbonInterface;

/**
 * Evaluates day of week targeting rules.
 */
class DayOfWeekEvaluator implements TargetingRuleEvaluator
{
    private const DAY_MAP = [
        'sun' => CarbonInterface::SUNDAY,
        'sunday' => CarbonInterface::SUNDAY,
        'mon' => CarbonInterface::MONDAY,
        'monday' => CarbonInterface::MONDAY,
        'tue' => CarbonInterface::TUESDAY,
        'tuesday' => CarbonInterface::TUESDAY,
        'wed' => CarbonInterface::WEDNESDAY,
        'wednesday' => CarbonInterface::WEDNESDAY,
        'thu' => CarbonInterface::THURSDAY,
        'thursday' => CarbonInterface::THURSDAY,
        'fri' => CarbonInterface::FRIDAY,
        'friday' => CarbonInterface::FRIDAY,
        'sat' => CarbonInterface::SATURDAY,
        'saturday' => CarbonInterface::SATURDAY,
    ];

    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::DayOfWeek->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $days = (array) ($rule['values'] ?? $rule['days'] ?? []);
        $operator = $rule['operator'] ?? 'in';

        if (empty($days)) {
            return true;
        }

        $timezone = $rule['timezone'] ?? $context->getTimezone();
        $now = $context->getCurrentTime($timezone);
        $currentDay = $now->dayOfWeek;

        $normalizedDays = $this->normalizeDays($days);

        return match ($operator) {
            'in' => in_array($currentDay, $normalizedDays, true),
            'not_in' => ! in_array($currentDay, $normalizedDays, true),
            default => in_array($currentDay, $normalizedDays, true),
        };
    }

    public function getType(): string
    {
        return TargetingRuleType::DayOfWeek->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];

        $days = $rule['values'] ?? $rule['days'] ?? null;
        if ($days === null || (is_array($days) && empty($days))) {
            $errors[] = 'Days are required';
        }

        return $errors;
    }

    /**
     * @param  array<mixed>  $days
     * @return array<int>
     */
    private function normalizeDays(array $days): array
    {
        $normalized = [];

        foreach ($days as $day) {
            if (is_numeric($day)) {
                $normalized[] = ((int) $day) % 7;

                continue;
            }

            $lookup = self::DAY_MAP[mb_strtolower((string) $day)] ?? null;

            if ($lookup !== null) {
                $normalized[] = $lookup;
            }
        }

        return array_values(array_unique($normalized));
    }
}
