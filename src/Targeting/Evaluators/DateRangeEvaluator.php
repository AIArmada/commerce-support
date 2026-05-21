<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;
use AIArmada\CommerceSupport\Targeting\Exceptions\TargetingRuleEvaluationException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Evaluates date range targeting rules.
 */
class DateRangeEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === TargetingRuleType::DateRange->value;
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $operator = $rule['operator'] ?? 'between';
        $timezone = $rule['timezone'] ?? $context->getTimezone();
        $now = $context->getCurrentTime($timezone);

        try {
            return match ($operator) {
                'between' => $this->evaluateBetween($rule, $now),
                'before' => $this->evaluateBefore($rule, $now),
                'after' => $this->evaluateAfter($rule, $now),
                default => false,
            };
        } catch (TargetingRuleEvaluationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw TargetingRuleEvaluationException::forRule(
                TargetingRuleType::DateRange->value,
                'unexpected runtime error',
                $exception,
            );
        }
    }

    public function getType(): string
    {
        return TargetingRuleType::DateRange->value;
    }

    public function validate(array $rule): array
    {
        $errors = [];
        $operator = $rule['operator'] ?? 'between';

        if ($operator === 'between') {
            if (! isset($rule['start']) && ! isset($rule['start_date'])) {
                $errors[] = 'Start date is required for between operator';
            }
            if (! isset($rule['end']) && ! isset($rule['end_date'])) {
                $errors[] = 'End date is required for between operator';
            }
        } elseif ($operator === 'before') {
            if (! isset($rule['date']) && ! isset($rule['end']) && ! isset($rule['end_date'])) {
                $errors[] = 'Date is required for before operator';
            }
        } elseif ($operator === 'after') {
            if (! isset($rule['date']) && ! isset($rule['start']) && ! isset($rule['start_date'])) {
                $errors[] = 'Date is required for after operator';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function evaluateBetween(array $rule, Carbon $now): bool
    {
        $startDate = $this->parseDate($rule['start'] ?? $rule['start_date'] ?? null, 'start');
        $endDate = $this->parseDate($rule['end'] ?? $rule['end_date'] ?? null, 'end');

        return $now->betweenIncluded($startDate, $endDate);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function evaluateBefore(array $rule, Carbon $now): bool
    {
        $date = $this->parseDate($rule['date'] ?? $rule['end'] ?? $rule['end_date'] ?? null, 'date');

        return $now->lessThan($date);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function evaluateAfter(array $rule, Carbon $now): bool
    {
        $date = $this->parseDate($rule['date'] ?? $rule['start'] ?? $rule['start_date'] ?? null, 'date');

        return $now->greaterThan($date);
    }

    private function parseDate(mixed $date, string $field): CarbonImmutable
    {
        if ($date === null) {
            throw TargetingRuleEvaluationException::forRule(
                TargetingRuleType::DateRange->value,
                sprintf('missing required date field "%s"', $field),
            );
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (Throwable $exception) {
            throw TargetingRuleEvaluationException::forRule(
                TargetingRuleType::DateRange->value,
                sprintf('invalid "%s" value "%s"', $field, is_scalar($date) ? (string) $date : get_debug_type($date)),
                $exception,
            );
        }
    }
}
