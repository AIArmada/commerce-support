<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Context;

use Illuminate\Database\Eloquent\Model;

/**
 * User-specific context for targeting evaluation.
 *
 * Encapsulates all user-related data used in targeting rules:
 * - User segments and roles
 * - First purchase status
 * - Customer lifetime value
 * - Order history
 * - Custom attributes
 */
readonly class UserContext
{
    /**
     * @param  Model|null  $user  The user model
     * @param  array<string>  $segments  User segments or roles
     * @param  bool  $isFirstPurchase  First time buyer flag
     * @param  int  $lifetimeValue  Customer lifetime value in minor units
     * @param  int  $orderCount  Total order count
     * @param  array<string, mixed>  $attributes  Additional user attributes
     */
    public function __construct(
        public ?Model $user = null,
        public array $segments = [],
        public bool $isFirstPurchase = true,
        public int $lifetimeValue = 0,
        public int $orderCount = 0,
        public array $attributes = [],
    ) {}

    /**
     * Create context from a user model.
     */
    public static function fromUser(?Model $user, array $metadata = []): self
    {
        if ($user === null) {
            return new self(
                segments: ['guest'],
                isFirstPurchase: true,
            );
        }

        return new self(
            user: $user,
            segments: self::extractSegments($user),
            isFirstPurchase: self::extractIsFirstPurchase($user, $metadata),
            lifetimeValue: self::extractLifetimeValue($user, $metadata),
            orderCount: self::extractOrderCount($user),
            attributes: self::extractAttributes($user),
        );
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function hasSegment(string $segment): bool
    {
        return in_array($segment, $this->segments, true);
    }

    public function hasAnySegment(array $segments): bool
    {
        return ! empty(array_intersect($this->segments, $segments));
    }

    public function hasAllSegments(array $segments): bool
    {
        return empty(array_diff($segments, $this->segments));
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @return array<string>
     */
    private static function extractSegments(Model $user): array
    {
        if (method_exists($user, 'getSegments')) {
            return $user->getSegments();
        }

        if (property_exists($user, 'segments') || isset($user->segments)) {
            $segments = $user->segments;

            return is_array($segments) ? $segments : [];
        }

        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }

        return [];
    }

    private static function extractIsFirstPurchase(Model $user, array $metadata): bool
    {
        if (isset($metadata['is_first_purchase'])) {
            return (bool) $metadata['is_first_purchase'];
        }

        $isFirstPurchase = $user->getAttribute('is_first_purchase');
        if ($isFirstPurchase !== null) {
            return (bool) $isFirstPurchase;
        }

        if (method_exists($user, 'orders')) {
            return $user->orders()->count() === 0;
        }

        $totalOrders = $user->getAttribute('total_orders');
        if ($totalOrders !== null) {
            return (int) $totalOrders === 0;
        }

        return false;
    }

    private static function extractLifetimeValue(Model $user, array $metadata): int
    {
        if (isset($metadata['clv'])) {
            return (int) $metadata['clv'];
        }

        if (method_exists($user, 'getLifetimeValue')) {
            return (int) $user->getLifetimeValue();
        }

        $clv = $user->getAttribute('customer_lifetime_value')
            ?? $user->getAttribute('lifetime_value')
            ?? $user->getAttribute('clv')
            ?? $user->getAttribute('total_spent');

        return (int) ($clv ?? 0);
    }

    private static function extractOrderCount(Model $user): int
    {
        if (method_exists($user, 'orders')) {
            return $user->orders()->count();
        }

        $totalOrders = $user->getAttribute('total_orders')
            ?? $user->getAttribute('order_count');

        return (int) ($totalOrders ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractAttributes(Model $user): array
    {
        $attributes = [];

        $commonAttributes = [
            'email', 'email_verified_at', 'created_at',
            'country', 'country_code', 'timezone',
            'preferred_currency', 'locale',
        ];

        foreach ($commonAttributes as $attr) {
            $value = $user->getAttribute($attr);
            if ($value !== null) {
                $attributes[$attr] = $value;
            }
        }

        return $attributes;
    }
}
