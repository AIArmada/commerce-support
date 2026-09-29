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
│   - UTM/Attribution       - ... and 17 more                     │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

Every value in `TargetingRuleType` has a matching evaluator in
`src/Targeting/Evaluators`, registered by `TargetingEngine::registerDefaultEvaluators()`:
`user_segment`, `user_attribute`, `first_purchase`, `clv`, `cart_value`,
`cart_quantity`, `product_in_cart`, `product_quantity`, `category_in_cart`,
`metadata`, `item_attribute`, `item_constraint`, `coupon_usage_limit`,
`payment_method`, `time_window`, `day_of_week`, `date_range`, `channel`,
`device`, `geographic`, `referrer`, `referral_source`, `currency`.

Use `TargetingRuleType::options()` / `grouped()` for the canonical label sets and
`getOperators()` for the operator vocabulary of a given rule type.

## Quick Start

```php
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\TargetingContext;

$engine = app(TargetingEngineInterface::class);

// Create context from cart
$context = TargetingContext::fromCart($cart);

// Evaluate targeting configuration
$targeting = [
    'mode' => 'all',
    'rules' => [
        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
        ['type' => 'user_segment', 'operator' => 'in', 'values' => ['vip', 'premium']],
    ],
];

$eligible = $engine->evaluate($targeting, $context);
```

Empty targeting (`[]`) means “no restrictions” and returns `true`. Non-empty invalid targeting fails closed and returns `false`; validate admin-authored targeting before storing it.

For non-custom modes (`all` / `any`), `rules` must be present and non-empty. Payloads like `['mode' => 'all']` or `['mode' => 'any', 'rules' => []]` are invalid and fail closed.

## Evaluation Modes

### All Mode (AND Logic)

All rules must pass:

```php
// Convenience helpers on the concrete TargetingEngine
$eligible = app(\AIArmada\CommerceSupport\Targeting\TargetingEngine::class)->evaluateAll($rules, $context);
// OR
$eligible = $engine->evaluate([
    'mode' => 'all',
    'rules' => $rules,
], $context);
```

### Any Mode (OR Logic)

At least one rule must pass:

```php
$eligible = app(\AIArmada\CommerceSupport\Targeting\TargetingEngine::class)->evaluateAny($rules, $context);
// OR
$eligible = $engine->evaluate([
    'mode' => 'any',
    'rules' => $rules,
], $context);
```

### Custom Mode (Boolean Expression)

Complex combinations using AND, OR, NOT:

```php
$targeting = [
    'mode' => 'custom',
    'expression' => [
        'or' => [
            [
                'and' => [
                    ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
                    ['type' => 'user_segment', 'operator' => 'in', 'values' => ['vip']],
                ],
            ],
            [
                'and' => [
                    ['type' => 'day_of_week', 'operator' => 'in', 'values' => ['saturday', 'sunday']],
                    ['type' => 'cart_quantity', 'operator' => '<=', 'value' => 10],
                ],
            ],
        ],
    ],
];

$eligible = $engine->evaluate($targeting, $context);
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
    cart: $cart,
    user: $user,
    request: request(),
    metadata: [
        'channel' => 'web',
        'device' => 'mobile',
        'country' => 'MY',
        'region' => 'KL',
        'city' => 'Kuala Lumpur',
        'referral_code' => 'SAVE20',
    ],
);
```

### Available Context Data

These are read through `TargetingContextInterface` accessors, not public properties.

| Accessor | Type | Description |
|----------|------|-------------|
| `getCartValue()` | `int` | Cart subtotal in integer minor units |
| `getCartQuantity()` | `int` | Total item quantity |
| `getProductIdentifiers()` | `array<string>` | SKUs, IDs, slugs |
| `getProductCategories()` | `array<string>` | Category slugs |
| `getUser()` | `?Model` | Authenticated user |
| `getUserSegments()` | `array<string>` | User segment tags |
| `getChannel()` | `string` | `web`, `mobile`, `api`, `pos` |
| `getDevice()` | `string` | `desktop`, `mobile`, `tablet` |
| `getCountry()` | `?string` | ISO 3166-1 alpha-2 country code |
| `getReferrer()` | `?string` | Referrer URL or source |
| `getTimezone()` | `string` | IANA timezone used by time rules |
| `getCurrentTime()` | `CarbonImmutable` | Evaluation timestamp |
| `getMetadata()` | `mixed` | Custom key-values passed to the constructor |
| `getCartMetadata()` | `mixed` | Metadata read from the **cart model** (not the `metadata` array) |

`TargetingContext` additionally exposes `getRegion()`, `getCity()`,
`getUtmSource()`, `getUtmMedium()`, `getUtmCampaign()`, `getCurrency()`,
`getProductQuantity()`, `getCouponCode()`, and `getPaymentMethod()`; these are
concrete-class methods rather than contract methods.

Money-valued rules (`cart_value`, `clv`, and `LineItemInterface` prices) are
integer minor units and must be paired with an ISO 4217 currency code. `currency`
is the rule type that matches that code.

### Trusted Input Sources

Channel, country, region, city, and timezone resolve in this order: explicit
`metadata` first, then (for country) the user model, then proxy/CDN headers,
then built-in defaults. Header values (`X-Channel`, `X-Sales-Channel`,
`CF-IPCountry`, `X-Country`, `X-Geo-Country`, `X-Region`, `X-City`,
`X-Timezone`) are client-spoofable, so they are **ignored by default**.
Discount-gating rules should pass server-resolved values via `metadata`
(storefront session, verified geo-IP) instead of relying on headers.

Enable header trust only when the deployment guarantees the headers are set
by trusted infrastructure (CDN, edge proxy, storefront backend):

```php
'targeting' => [
    'trust_proxy_headers' => true,
],
```

`Referer` and UTM values have no server-side equivalent and are always
treated as untrusted hints; avoid them in discount-gating rules.

Custom `and`/`or`/`not` expressions are capped at a nesting depth of 10 and
200 total nodes; deeper or larger expressions fail validation and evaluate
to `false`.

## Built-in Rule Types

### Cart Rules

#### cart_value
```php
['type' => 'cart_value', 'operator' => '>=', 'value' => 5000]
// Operators: =, !=, >, >=, <, <=, between (use min/max instead of value)
// value in integer minor units
```

#### cart_quantity
```php
['type' => 'cart_quantity', 'operator' => 'between', 'min' => 2, 'max' => 10]
```

#### product_in_cart
```php
['type' => 'product_in_cart', 'operator' => 'contains_any', 'values' => ['SKU-001', 'SKU-002']]
// Operators: in, not_in, contains_any, contains_all
```

#### category_in_cart
```php
['type' => 'category_in_cart', 'operator' => 'in', 'values' => ['electronics']]
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
['type' => 'user_segment', 'operator' => 'in', 'values' => ['vip', 'premium']]
```

#### first_purchase
```php
['type' => 'first_purchase', 'value' => true]
```

#### clv
```php
['type' => 'clv', 'operator' => '>=', 'value' => 100000]
// value in integer minor units
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

`date_range` is fail-closed: malformed or missing date fields are treated as rule evaluation failures, logged by the engine, and return `false` (not eligible).

#### time_window
```php
[
    'type' => 'time_window',
    'start' => '09:00',
    'end' => '17:00',
    'timezone' => 'Asia/Kuala_Lumpur'
]
```

#### day_of_week
```php
['type' => 'day_of_week', 'days' => ['monday', 'tuesday', 'wednesday']]
// or ['type' => 'day_of_week', 'values' => ['sat', 'sun']]
// Accepts full names, three-letter abbreviations, or 0-6 integers
// Operators: in (default), not_in
```

> **warning**
> There are no `weekday` / `weekend` group keywords. Unrecognized entries normalize away, so an all-unknown list never matches. Expand the days explicitly.

### Geographic Rules

#### geographic
```php
[
    'type' => 'geographic',
    'countries' => ['MY', 'SG', 'TH'],
    'operator' => 'in',        // or 'not_in' (default 'in')
]
// 'values' is accepted as an alias for 'countries'; comparison is case-insensitive
// The rule returns false when no country can be resolved
```

> **warning**
> This evaluator is country-only. `regions` and `exclude_countries` are not read and are silently ignored. Use `not_in` for country exclusion.

### Channel Rules

#### channel
```php
['type' => 'channel', 'operator' => 'in', 'values' => ['web', 'mobile']]
```

#### device
```php
['type' => 'device', 'operator' => 'in', 'values' => ['mobile', 'tablet']]
```

### Custom Rules

#### metadata
```php
[
    'type' => 'metadata',
    'key' => 'campaign',
    'operator' => '=',
    'value' => 'summer'
]
// Operators: exists, =, !=, contains, in, flag
// 'in' additionally requires a 'values' array
```

> **warning**
> `metadata` reads the **cart model's** metadata (`hasCartMetadata()` / `getCartMetadata()`), not the `metadata` array passed to `TargetingContext`. Keys that only exist in the constructor `metadata` array are reported as absent. Use `referral_source` or a custom evaluator for constructor metadata.

## Creating Custom Evaluators

### Implement the Interface

```php
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;

class LoyaltyPointsEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === $this->getType();
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        $user = $context->getUser();
        
        if (! $user) {
            return false;
        }

        $points = $user->loyalty_points ?? 0;
        $operator = $rule['operator'] ?? 'gte';
        $value = $rule['value'] ?? 0;

        return match ($operator) {
            '=' => $points === $value,
            '>=' => $points >= $value,
            '<=' => $points <= $value,
            default => false,
        };
    }

    public function getType(): string
    {
        return 'loyalty_points';
    }

    public function validate(array $rule): array
    {
        if (! isset($rule['value']) || ! is_numeric($rule['value'])) {
            return ['Value must be a number'];
        }

        return [];
    }
}
```

### Register the Evaluator

`TargetingEngineInterface` is bound as a container singleton, so register against
that binding. Resolving the concrete `TargetingEngine` class directly returns a
fresh, unshared instance and the registration is lost.

```php
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;

public function boot(): void
{
    app(TargetingEngineInterface::class)->registerEvaluator(new LoyaltyPointsEvaluator());
}
```

### Use the Custom Rule

```php
$rules = [
    ['type' => 'loyalty_points', 'operator' => '>=', 'value' => 1000],
];

$eligible = $engine->evaluate([
    'mode' => 'all',
    'rules' => $rules,
], $context);
```

## Validation

Validate rules before storing:

```php
$targeting = [
    'mode' => 'all',
    'rules' => [
        ['type' => 'cart_value', 'operator' => '>=', 'value' => 5000],
        ['type' => 'invalid_type', 'foo' => 'bar'],
    ],
];

$errors = $engine->validate($targeting);
// ['Rule 1: Unknown rule type: invalid_type']

$eligible = $engine->evaluate($targeting, $context);
// false: non-empty invalid targeting fails closed
```

## Real-world Examples

### Promotion Eligibility

```php
class Promotion extends Model
{
    public function isEligible(Cart $cart): bool
    {
        $engine = app(TargetingEngineInterface::class);
        $context = TargetingContext::fromCart($cart);

        return $engine->evaluate(
            [
                'mode' => $this->targeting_mode,
                'rules' => $this->targeting_rules,
            ],
            $context
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

        $engine = app(TargetingEngineInterface::class);
        $context = TargetingContext::fromCart($cart);

        return $engine->evaluate([
            'mode' => 'all',
            'rules' => $this->availability_rules,
        ], $context);
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
        'operator' => '>=',
        'value' => 10000,
    ],
    'vip_member' => [
        'type' => 'user_segment',
        'operator' => 'in',
        'values' => ['vip'],
    ],
    'from_malaysia' => [
        'type' => 'geographic',
        'countries' => ['MY'],
    ],
];

// VIP members from Malaysia get the sale anytime
// Others need min cart during sale period
$targeting = [
    'mode' => 'custom',
    'expression' => [
        'or' => [
            [
                'and' => [$rules['vip_member'], $rules['from_malaysia']],
            ],
            [
                'and' => [$rules['during_sale'], $rules['min_cart']],
            ],
        ],
    ],
];

$eligible = $engine->evaluate($targeting, $context);
```

## Performance Tips

1. **Order rules strategically** - Put cheap/likely-to-fail rules first in `all` mode
2. **Use `any` mode for fallbacks** - Put cheap/likely-to-pass rules first
3. **Cache context** - Create `TargetingContext` once per request
4. **Validate at save time** - Don't validate on every evaluation
