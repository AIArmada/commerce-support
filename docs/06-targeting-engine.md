---
title: Targeting Engine
---

# Targeting Engine

The Targeting Engine provides a powerful rule-based system for evaluating whether entities (promotions, vouchers, shipping methods, etc.) are applicable to a given context. It supports 23 built-in rule types, three evaluation modes, and custom boolean expressions.

## Overview

```
┌────────────────────────────────────────────────────────────────┐
│                     Targeting Engine                            │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│   TargetingContext ────► TargetingEngine ────► bool             │
│        │                       │                                │
│        ▼                       ▼                                │
│   - Cart value            23 Evaluators                         │
│   - User segments         - CartValueEvaluator                  │
│   - Channel/Device        - UserSegmentEvaluator                │
│   - Geographic data       - ProductQuantityEvaluator            │
│   - Date/Time             - PaymentMethodEvaluator              │
│   - Products/Categories   - CouponUsageLimitEvaluator           │
│   - Payment methods       - ReferralSourceEvaluator             │
│   - UTM/Attribution       - ... and 16 more                     │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

## Quick Start

```php
use AIArmada\CommerceSupport\Targeting\TargetingEngine;
use AIArmada\CommerceSupport\Targeting\TargetingContext;

$engine = app(TargetingEngine::class);

// Create context from cart
$context = TargetingContext::fromCart($cart);

// Evaluate rules
$rules = [
    ['type' => 'cart_value', 'operator' => 'gte', 'value' => 5000],
    ['type' => 'user_segment', 'segments' => ['vip', 'premium']],
];

$eligible = $engine->evaluate($rules, $context, mode: 'all');
```

## Evaluation Modes

### All Mode (AND Logic)

All rules must pass:

```php
$eligible = $engine->evaluateAll($rules, $context);
// OR
$eligible = $engine->evaluate($rules, $context, mode: 'all');
```

### Any Mode (OR Logic)

At least one rule must pass:

```php
$eligible = $engine->evaluateAny($rules, $context);
// OR
$eligible = $engine->evaluate($rules, $context, mode: 'any');
```

### Custom Mode (Boolean Expression)

Complex combinations using AND, OR, NOT:

```php
$rules = [
    'min_value' => ['type' => 'cart_value', 'operator' => 'gte', 'value' => 5000],
    'vip_user' => ['type' => 'user_segment', 'segments' => ['vip']],
    'weekend' => ['type' => 'day_of_week', 'days' => ['saturday', 'sunday']],
    'not_bulk' => ['type' => 'cart_quantity', 'operator' => 'lte', 'value' => 10],
];

// (min_value AND vip_user) OR (weekend AND not_bulk)
$expression = '(min_value AND vip_user) OR (weekend AND not_bulk)';

$eligible = $engine->evaluateExpression($rules, $expression, $context);
```

## TargetingContext

The context object provides all data needed for rule evaluation.

### Creating Context

```php
use AIArmada\CommerceSupport\Targeting\TargetingContext;

// From cart (auto-resolves user and request)
$context = TargetingContext::fromCart($cart);

// Manual construction
$context = new TargetingContext(
    cartValue: 15000,           // Cart total in cents
    cartQuantity: 3,            // Total items
    productIdentifiers: ['SKU-001', 'SKU-002'],
    productCategories: ['electronics', 'accessories'],
    user: $user,
    userSegments: ['premium', 'returning'],
    channel: 'web',
    device: 'mobile',
    country: 'MY',
    region: 'KL',
    city: 'Kuala Lumpur',
    metadata: ['referral_code' => 'SAVE20'],
    currentTime: now(),
);
```

### Available Context Data

| Property | Type | Description |
|----------|------|-------------|
| `cartValue` | `int` | Cart total in cents |
| `cartQuantity` | `int` | Total item quantity |
| `productIdentifiers` | `array<string>` | SKUs, IDs, slugs |
| `productCategories` | `array<string>` | Category slugs |
| `user` | `?Model` | Authenticated user |
| `userSegments` | `array<string>` | User segment tags |
| `channel` | `?string` | `web`, `mobile`, `api`, `pos` |
| `device` | `?string` | `desktop`, `mobile`, `tablet` |
| `country` | `?string` | ISO country code |
| `region` | `?string` | State/province |
| `city` | `?string` | City name |
| `metadata` | `array` | Custom key-values |
| `currentTime` | `Carbon` | Evaluation timestamp |

## Built-in Rule Types

### Cart Rules

#### cart_value
```php
['type' => 'cart_value', 'operator' => 'gte', 'value' => 5000]
// Operators: eq, neq, gt, gte, lt, lte, between
// value in cents
```

#### cart_quantity
```php
['type' => 'cart_quantity', 'operator' => 'between', 'min' => 2, 'max' => 10]
```

#### product_in_cart
```php
['type' => 'product_in_cart', 'products' => ['SKU-001', 'SKU-002'], 'match' => 'any']
// match: 'any' (default) or 'all'
```

#### category_in_cart
```php
['type' => 'category_in_cart', 'categories' => ['electronics'], 'match' => 'any']
```

#### product_quantity
```php
// Check quantity of specific product
['type' => 'product_quantity', 'product' => 'SKU-001', 'operator' => 'gte', 'value' => 3]

// Check combined quantity of multiple products
['type' => 'product_quantity', 'products' => ['SKU-001', 'SKU-002'], 'operator' => 'between', 'min' => 2, 'max' => 5]
```

### Payment Rules

#### payment_method
```php
// Only for specific payment methods
['type' => 'payment_method', 'methods' => ['credit_card', 'debit_card']]

// Exclude payment methods
['type' => 'payment_method', 'exclude' => ['cod', 'cash_on_delivery']]
```

#### coupon_usage_limit
```php
// Limit to 3 uses per customer
['type' => 'coupon_usage_limit', 'code' => 'SAVE20', 'max_uses' => 3]

// First-time use only
['type' => 'coupon_usage_limit', 'max_uses' => 1]
```

### Attribution Rules

#### referral_source
```php
// From Google Ads
['type' => 'referral_source', 'utm_source' => 'google', 'utm_medium' => 'cpc']

// From specific campaign
['type' => 'referral_source', 'utm_campaign' => 'black_friday_2024']

// From referrer domain
['type' => 'referral_source', 'referrer_domain' => 'instagram.com']

// Multiple domains
['type' => 'referral_source', 'referrer_domain' => ['facebook.com', 'instagram.com']]

// Affiliate/partner traffic
['type' => 'referral_source', 'sources' => ['affiliate', 'partner']]

// Exclude sources
['type' => 'referral_source', 'exclude_sources' => ['spam', 'bot']]
```

### User Rules

#### user_segment
```php
['type' => 'user_segment', 'segments' => ['vip', 'premium'], 'match' => 'any']
```

#### first_time_buyer
```php
['type' => 'first_time_buyer', 'value' => true]
```

#### user_order_count
```php
['type' => 'user_order_count', 'operator' => 'gte', 'value' => 5]
```

#### user_lifetime_value
```php
['type' => 'user_lifetime_value', 'operator' => 'gte', 'value' => 100000]
// value in cents
```

### Time Rules

#### date_range
```php
[
    'type' => 'date_range',
    'start' => '2024-12-01',
    'end' => '2024-12-31',
    'timezone' => 'Asia/Kuala_Lumpur'  // optional
]
```

#### time_range
```php
[
    'type' => 'time_range',
    'start' => '09:00',
    'end' => '17:00',
    'timezone' => 'Asia/Kuala_Lumpur'
]
```

#### day_of_week
```php
['type' => 'day_of_week', 'days' => ['monday', 'tuesday', 'wednesday']]
// or ['type' => 'day_of_week', 'days' => ['weekday']]
// or ['type' => 'day_of_week', 'days' => ['weekend']]
```

### Geographic Rules

#### geographic
```php
[
    'type' => 'geographic',
    'countries' => ['MY', 'SG', 'TH'],
    'regions' => ['Selangor', 'KL'],  // optional
    'exclude_countries' => ['US'],     // optional
]
```

### Channel Rules

#### channel
```php
['type' => 'channel', 'channels' => ['web', 'mobile']]
```

#### device
```php
['type' => 'device', 'devices' => ['mobile', 'tablet']]
```

### Custom Rules

#### metadata
```php
[
    'type' => 'metadata',
    'key' => 'referral_code',
    'operator' => 'eq',
    'value' => 'SAVE20'
]
// Operators: eq, neq, contains, not_contains, exists, not_exists
```

#### custom (Callable)
```php
[
    'type' => 'custom',
    'evaluator' => fn(TargetingContext $ctx) => $ctx->getUser()?->is_verified ?? false
]
```

## Creating Custom Evaluators

### Implement the Interface

```php
use AIArmada\CommerceSupport\Targeting\Contracts\RuleEvaluatorInterface;
use AIArmada\CommerceSupport\Targeting\TargetingContext;

class LoyaltyPointsEvaluator implements RuleEvaluatorInterface
{
    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $user = $context->getUser();
        
        if (! $user) {
            return false;
        }

        $points = $user->loyalty_points ?? 0;
        $operator = $rule['operator'] ?? 'gte';
        $value = $rule['value'] ?? 0;

        return match ($operator) {
            'eq' => $points === $value,
            'gte' => $points >= $value,
            'lte' => $points <= $value,
            default => false,
        };
    }

    public function getType(): string
    {
        return 'loyalty_points';
    }

    public function validate(array $rule): bool
    {
        return isset($rule['operator'], $rule['value'])
            && is_numeric($rule['value']);
    }
}
```

### Register the Evaluator

```php
// In a service provider
public function boot(): void
{
    $engine = app(TargetingEngine::class);
    $engine->registerEvaluator(new LoyaltyPointsEvaluator());
}
```

### Use the Custom Rule

```php
$rules = [
    ['type' => 'loyalty_points', 'operator' => 'gte', 'value' => 1000],
];

$eligible = $engine->evaluate($rules, $context);
```

## Validation

Validate rules before storing:

```php
$rules = [
    ['type' => 'cart_value', 'operator' => 'gte', 'value' => 5000],
    ['type' => 'invalid_type', 'foo' => 'bar'],  // Invalid
];

$valid = $engine->validate($rules);
// Returns false if any rule is invalid

// Get validation errors
$errors = $engine->getValidationErrors($rules);
// ['Rule 1 (invalid_type): Unknown rule type']
```

## Real-world Examples

### Promotion Eligibility

```php
class Promotion extends Model
{
    public function isEligible(Cart $cart): bool
    {
        $engine = app(TargetingEngine::class);
        $context = TargetingContext::fromCart($cart);

        return $engine->evaluate(
            $this->targeting_rules,
            $context,
            mode: $this->targeting_mode
        );
    }
}
```

### Shipping Method Availability

```php
class ShippingMethod extends Model
{
    public function isAvailableFor(Cart $cart): bool
    {
        if (empty($this->availability_rules)) {
            return true;
        }

        $engine = app(TargetingEngine::class);
        $context = TargetingContext::fromCart($cart);

        return $engine->evaluateAll($this->availability_rules, $context);
    }
}
```

### Flash Sale with Complex Conditions

```php
$rules = [
    'during_sale' => [
        'type' => 'date_range',
        'start' => '2024-11-29 00:00:00',
        'end' => '2024-11-29 23:59:59',
    ],
    'min_cart' => [
        'type' => 'cart_value',
        'operator' => 'gte',
        'value' => 10000,
    ],
    'vip_member' => [
        'type' => 'user_segment',
        'segments' => ['vip'],
    ],
    'from_malaysia' => [
        'type' => 'geographic',
        'countries' => ['MY'],
    ],
];

// VIP members from Malaysia get the sale anytime
// Others need min cart during sale period
$expression = '(vip_member AND from_malaysia) OR (during_sale AND min_cart)';

$eligible = $engine->evaluateExpression($rules, $expression, $context);
```

## Performance Tips

1. **Order rules strategically** - Put cheap/likely-to-fail rules first in `all` mode
2. **Use `any` mode for fallbacks** - Put cheap/likely-to-pass rules first
3. **Cache context** - Create `TargetingContext` once per request
4. **Validate at save time** - Don't validate on every evaluation
