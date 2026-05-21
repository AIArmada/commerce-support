<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingMode;
use AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType;
use AIArmada\CommerceSupport\Targeting\Exceptions\TargetingRuleEvaluationException;

/**
 * Engine for evaluating targeting rules.
 *
 * Supports three evaluation modes:
 * - all: All rules must match (AND logic)
 * - any: Any rule must match (OR logic)
 * - custom: Boolean expression with AND, OR, NOT operators
 */
class TargetingEngine implements TargetingEngineInterface
{
    /**
     * @var array<string, TargetingRuleEvaluator>
     */
    private array $evaluators = [];

    public function __construct()
    {
        $this->registerDefaultEvaluators();
    }

    public function registerEvaluator(TargetingRuleEvaluator $evaluator): self
    {
        $this->evaluators[$evaluator->getType()] = $evaluator;

        return $this;
    }

    public function getEvaluator(string $type): ?TargetingRuleEvaluator
    {
        return $this->evaluators[$type] ?? null;
    }

    /**
     * @return array<string, TargetingRuleEvaluator>
     */
    public function getEvaluators(): array
    {
        return $this->evaluators;
    }

    /**
     * @param  array<string, mixed>  $targeting
     */
    public function evaluate(array $targeting, TargetingContextInterface $context): bool
    {
        if (empty($targeting)) {
            return true;
        }

        if ($this->validate($targeting) !== []) {
            return false;
        }

        $modeValue = (string) ($targeting['mode'] ?? TargetingMode::All->value);
        $mode = TargetingMode::from($modeValue);

        return match ($mode) {
            TargetingMode::All => $this->evaluateAll($targeting['rules'] ?? [], $context),
            TargetingMode::Any => $this->evaluateAny($targeting['rules'] ?? [], $context),
            TargetingMode::Custom => $this->evaluateExpression($targeting['expression'] ?? [], $context),
        };
    }

    /**
     * Evaluate with ALL mode - all rules must pass.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    public function evaluateAll(array $rules, TargetingContextInterface $context): bool
    {
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                return false;
            }

            if (! $this->evaluateRule($rule, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate with ANY mode - at least one rule must pass.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    public function evaluateAny(array $rules, TargetingContextInterface $context): bool
    {
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                return false;
            }

            if ($this->evaluateRule($rule, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate a custom boolean expression.
     *
     * Supports nested AND, OR, NOT operators:
     * {
     *   "and": [
     *     {"type": "cart_value", "operator": ">=", "value": 5000},
     *     {"or": [
     *       {"type": "user_segment", "operator": "in", "values": ["vip"]},
     *       {"type": "first_purchase", "operator": "=", "value": true}
     *     ]},
     *     {"not": {"type": "channel", "operator": "=", "value": "pos"}}
     *   ]
     * }
     *
     * @param  array<string, mixed>  $expression
     */
    public function evaluateExpression(array $expression, TargetingContextInterface $context): bool
    {
        if (empty($expression)) {
            return false;
        }

        if (isset($expression['and'])) {
            $subExpressions = $expression['and'];
            if (! is_array($subExpressions) || $subExpressions === []) {
                return false;
            }

            foreach ($subExpressions as $subExpr) {
                if (! is_array($subExpr)) {
                    return false;
                }

                if (! $this->evaluateExpression($subExpr, $context)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($expression['or'])) {
            $subExpressions = $expression['or'];
            if (! is_array($subExpressions) || $subExpressions === []) {
                return false;
            }

            foreach ($subExpressions as $subExpr) {
                if (! is_array($subExpr)) {
                    return false;
                }

                if ($this->evaluateExpression($subExpr, $context)) {
                    return true;
                }
            }

            return false;
        }

        if (isset($expression['not'])) {
            $subExpr = $expression['not'];
            if (! is_array($subExpr) || $subExpr === []) {
                return false;
            }

            return ! $this->evaluateExpression($subExpr, $context);
        }

        return $this->evaluateRule($expression, $context);
    }

    /**
     * Evaluate a single rule.
     *
     * @param  array<string, mixed>  $rule
     */
    public function evaluateRule(array $rule, TargetingContextInterface $context): bool
    {
        $type = $rule['type'] ?? '';

        if (! is_string($type) || $type === '') {
            return false;
        }

        $evaluator = $this->getEvaluator($type);

        if ($evaluator === null) {
            return false;
        }

        if ($evaluator->validate($rule) !== []) {
            return false;
        }

        try {
            return $evaluator->evaluate($rule, $context);
        } catch (TargetingRuleEvaluationException $exception) {
            logger()->warning('Targeting rule evaluation failed; failing closed.', [
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $targeting
     * @return array<string>
     */
    public function validate(array $targeting): array
    {
        $errors = [];

        $mode = $targeting['mode'] ?? TargetingMode::All->value;
        if (! is_string($mode) || TargetingMode::tryFrom($mode) === null) {
            $errors[] = sprintf('Invalid targeting mode: %s', is_scalar($mode) ? (string) $mode : get_debug_type($mode));

            return $errors;
        }

        if ($mode === TargetingMode::Custom->value) {
            if (! isset($targeting['expression']) || ! is_array($targeting['expression']) || $targeting['expression'] === []) {
                $errors[] = 'Custom mode requires an expression';
            } else {
                $errors = array_merge($errors, $this->validateExpression($targeting['expression']));
            }
        } else {
            if (! array_key_exists('rules', $targeting)) {
                $errors[] = 'Non-custom targeting requires a non-empty rules array';

                return $errors;
            }

            $rules = $targeting['rules'];
            if (! is_array($rules)) {
                $errors[] = 'Rules must be an array';
            } elseif ($rules === []) {
                $errors[] = 'Non-custom targeting requires at least one rule';
            } else {
                foreach ($rules as $i => $rule) {
                    if (! is_array($rule)) {
                        $errors[] = "Rule {$i}: Rule must be an array";

                        continue;
                    }

                    $ruleErrors = $this->validateRule($rule);
                    foreach ($ruleErrors as $error) {
                        $errors[] = "Rule {$i}: {$error}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $expression
     * @return array<string>
     */
    private function validateExpression(array $expression): array
    {
        $errors = [];

        if ($expression === []) {
            return ['Expression cannot be empty'];
        }

        if (isset($expression['and'])) {
            if (! is_array($expression['and'])) {
                $errors[] = 'AND expression must be an array';
            } else {
                if ($expression['and'] === []) {
                    $errors[] = 'AND expression must contain at least one expression';
                }

                foreach ($expression['and'] as $index => $subExpr) {
                    if (! is_array($subExpr)) {
                        $errors[] = "AND expression {$index} must be an object";

                        continue;
                    }

                    $errors = array_merge($errors, $this->validateExpression($subExpr));
                }
            }
        } elseif (isset($expression['or'])) {
            if (! is_array($expression['or'])) {
                $errors[] = 'OR expression must be an array';
            } else {
                if ($expression['or'] === []) {
                    $errors[] = 'OR expression must contain at least one expression';
                }

                foreach ($expression['or'] as $index => $subExpr) {
                    if (! is_array($subExpr)) {
                        $errors[] = "OR expression {$index} must be an object";

                        continue;
                    }

                    $errors = array_merge($errors, $this->validateExpression($subExpr));
                }
            }
        } elseif (isset($expression['not'])) {
            if (! is_array($expression['not']) || $expression['not'] === []) {
                $errors[] = 'NOT expression must be an object';
            } else {
                $errors = array_merge($errors, $this->validateExpression($expression['not']));
            }
        } else {
            $errors = array_merge($errors, $this->validateRule($expression));
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string>
     */
    private function validateRule(array $rule): array
    {
        $errors = [];

        $type = $rule['type'] ?? '';

        if (! is_string($type) || $type === '') {
            $errors[] = 'Rule type is required';

            return $errors;
        }

        $ruleType = TargetingRuleType::tryFrom($type);
        $evaluator = $this->getEvaluator($type);

        if ($ruleType === null && $evaluator === null) {
            $errors[] = "Unknown rule type: {$type}";

            return $errors;
        }

        $operator = $rule['operator'] ?? '';
        $validOperators = $ruleType?->getOperators() ?? [];

        if ($operator !== '' && (! is_string($operator) || ($validOperators !== [] && ! isset($validOperators[$operator])))) {
            $errors[] = sprintf(
                "Invalid operator '%s' for rule type '%s'",
                is_scalar($operator) ? (string) $operator : get_debug_type($operator),
                $type,
            );
        }

        if ($evaluator !== null) {
            $errors = array_merge($errors, $evaluator->validate($rule));
        } else {
            $errors[] = "No evaluator registered for rule type: {$type}";
        }

        return $errors;
    }

    /**
     * Register default evaluators for all rule types.
     */
    private function registerDefaultEvaluators(): void
    {
        $evaluatorClasses = [
            Evaluators\UserSegmentEvaluator::class,
            Evaluators\UserAttributeEvaluator::class,
            Evaluators\FirstPurchaseEvaluator::class,
            Evaluators\CustomerLifetimeValueEvaluator::class,
            Evaluators\CartValueEvaluator::class,
            Evaluators\CartQuantityEvaluator::class,
            Evaluators\ProductInCartEvaluator::class,
            Evaluators\CategoryInCartEvaluator::class,
            Evaluators\MetadataEvaluator::class,
            Evaluators\ItemAttributeEvaluator::class,
            Evaluators\ItemConstraintEvaluator::class,
            Evaluators\TimeWindowEvaluator::class,
            Evaluators\DayOfWeekEvaluator::class,
            Evaluators\DateRangeEvaluator::class,
            Evaluators\ChannelEvaluator::class,
            Evaluators\DeviceEvaluator::class,
            Evaluators\GeographicEvaluator::class,
            Evaluators\ReferrerEvaluator::class,
            Evaluators\CurrencyEvaluator::class,
            // New evaluators
            Evaluators\ProductQuantityEvaluator::class,
            Evaluators\PaymentMethodEvaluator::class,
            Evaluators\CouponUsageLimitEvaluator::class,
            Evaluators\ReferralSourceEvaluator::class,
        ];

        foreach ($evaluatorClasses as $class) {
            if (class_exists($class)) {
                $this->registerEvaluator(new $class);
            }
        }
    }

    /**
     * Get all registered evaluator types.
     *
     * @return array<string>
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->evaluators);
    }
}
