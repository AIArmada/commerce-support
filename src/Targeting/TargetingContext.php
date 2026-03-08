<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting;

use AIArmada\CommerceSupport\Targeting\Context\CartContext;
use AIArmada\CommerceSupport\Targeting\Context\EnvironmentContext;
use AIArmada\CommerceSupport\Targeting\Context\UserContext;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Context object containing all information needed for targeting evaluation.
 *
 * This class now delegates to focused context classes for better organization:
 * - CartContext: Cart value, quantity, products, categories
 * - UserContext: Segments, lifetime value, order count
 * - EnvironmentContext: Channel, device, geographic, UTM
 */
readonly class TargetingContext implements TargetingContextInterface
{
    public CartContext $cartContext;

    public UserContext $userContext;

    public EnvironmentContext $environmentContext;

    /**
     * @param  mixed  $cart  The cart instance
     * @param  Model|null  $user  The user model
     * @param  Request|null  $request  The HTTP request
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public mixed $cart,
        public ?Model $user = null,
        public ?Request $request = null,
        public array $metadata = [],
    ) {
        $this->cartContext = CartContext::fromCart($cart);
        $this->userContext = UserContext::fromUser($user, $metadata);
        $this->environmentContext = EnvironmentContext::fromRequest($request, $metadata);
    }

    /**
     * Create context from cart with auto-resolved user and request.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function fromCart(mixed $cart, array $metadata = []): self
    {
        $user = null;
        $request = null;

        if (function_exists('auth') && auth()->check()) {
            $user = auth()->user();
        }

        if (function_exists('request')) {
            $request = request();
        }

        return new self($cart, $user, $request, $metadata);
    }

    public function getCart(): mixed
    {
        return $this->cart;
    }

    public function getUser(): ?Model
    {
        return $this->user;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * @return array<string>
     */
    public function getUserSegments(): array
    {
        if ($this->user === null) {
            return ['guest'];
        }

        if (method_exists($this->user, 'getSegments')) {
            return $this->user->getSegments();
        }

        if (property_exists($this->user, 'segments') || isset($this->user->segments)) {
            $segments = $this->user->segments;

            return is_array($segments) ? $segments : [];
        }

        if (method_exists($this->user, 'getRoleNames')) {
            return $this->user->getRoleNames()->toArray();
        }

        return [];
    }

    public function getUserAttribute(string $attribute): mixed
    {
        if ($this->user === null) {
            return null;
        }

        if (method_exists($this->user, 'getAttribute')) {
            return $this->user->getAttribute($attribute);
        }

        return $this->user->{$attribute} ?? null;
    }

    public function isFirstPurchase(): bool
    {
        if ($this->user === null) {
            return true;
        }

        if (isset($this->metadata['is_first_purchase'])) {
            return (bool) $this->metadata['is_first_purchase'];
        }

        $isFirstPurchase = $this->getUserAttribute('is_first_purchase');
        if ($isFirstPurchase !== null) {
            return (bool) $isFirstPurchase;
        }

        if (method_exists($this->user, 'orders')) {
            return $this->user->orders()->count() === 0;
        }

        $totalOrders = $this->getUserAttribute('total_orders');

        if ($totalOrders !== null) {
            return (int) $totalOrders === 0;
        }

        return false;
    }

    public function getCustomerLifetimeValue(): int
    {
        if ($this->user === null) {
            return 0;
        }

        if (isset($this->metadata['clv'])) {
            return (int) $this->metadata['clv'];
        }

        if (method_exists($this->user, 'getLifetimeValue')) {
            return (int) $this->user->getLifetimeValue();
        }

        $clv = $this->getUserAttribute('customer_lifetime_value')
            ?? $this->getUserAttribute('lifetime_value')
            ?? $this->getUserAttribute('clv')
            ?? $this->getUserAttribute('total_spent');

        return (int) ($clv ?? 0);
    }

    public function getCartValue(): int
    {
        if ($this->cart === null) {
            return 0;
        }

        if (method_exists($this->cart, 'getRawSubtotalWithoutConditions')) {
            return $this->cart->getRawSubtotalWithoutConditions();
        }

        if (method_exists($this->cart, 'getSubtotal')) {
            return (int) $this->cart->getSubtotal();
        }

        return 0;
    }

    public function getCartQuantity(): int
    {
        if ($this->cart === null) {
            return 0;
        }

        if (method_exists($this->cart, 'getItems')) {
            $items = $this->cart->getItems();
            if ($items instanceof Collection) {
                return $items->sum(fn ($item) => $item->quantity ?? 1);
            }
        }

        if (method_exists($this->cart, 'getTotalQuantity')) {
            return $this->cart->getTotalQuantity();
        }

        return 0;
    }

    /**
     * @return array<string>
     */
    public function getProductIdentifiers(): array
    {
        if ($this->cart === null || ! method_exists($this->cart, 'getItems')) {
            return [];
        }

        $items = $this->cart->getItems();
        if (! $items instanceof Collection) {
            return [];
        }

        return $items
            ->map(function ($item): ?string {
                $sku = $item->getAttribute('sku') ?? null;
                if ($sku !== null) {
                    return (string) $sku;
                }

                $model = $item->associatedModel ?? null;
                if ($model === null) {
                    return $item->id ?? null;
                }

                if (method_exists($model, 'getSku')) {
                    return $model->getSku();
                }

                if (is_object($model) && property_exists($model, 'sku')) {
                    return $model->sku;
                }

                return $item->id ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string>
     */
    public function getProductCategories(): array
    {
        if ($this->cart === null || ! method_exists($this->cart, 'getItems')) {
            return [];
        }

        $items = $this->cart->getItems();
        if (! $items instanceof Collection) {
            return [];
        }

        return $items
            ->flatMap(function ($item): array {
                $category = $item->getAttribute('category') ?? null;
                if ($category !== null) {
                    return is_array($category) ? $category : [(string) $category];
                }

                $model = $item->associatedModel ?? null;
                if ($model === null) {
                    return [];
                }

                if (method_exists($model, 'getCategories')) {
                    return $model->getCategories();
                }

                if ($model instanceof Model && method_exists($model, 'categories')) {
                    $categories = $model->getRelationValue('categories');

                    if ($categories instanceof Collection) {
                        return $categories->pluck('slug')->all();
                    }
                }

                if (is_object($model) && property_exists($model, 'category')) {
                    return [$model->category];
                }

                if (is_object($model) && property_exists($model, 'category_id')) {
                    return [(string) $model->category_id];
                }

                return [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function getChannel(): string
    {
        if (isset($this->metadata['channel'])) {
            return (string) $this->metadata['channel'];
        }

        if ($this->request !== null) {
            $channel = $this->request->header('X-Channel')
                ?? $this->request->header('X-Sales-Channel');

            if ($channel !== null) {
                return is_array($channel) ? $channel[0] : $channel;
            }
        }

        return 'web';
    }

    public function getDevice(): string
    {
        if (isset($this->metadata['device'])) {
            return (string) $this->metadata['device'];
        }

        if ($this->request === null) {
            return 'desktop';
        }

        $userAgent = $this->request->userAgent() ?? '';

        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile|wpdesktop/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    public function getCountry(): ?string
    {
        if (isset($this->metadata['country'])) {
            return (string) $this->metadata['country'];
        }

        if ($this->request !== null) {
            $country = $this->request->header('CF-IPCountry')
                ?? $this->request->header('X-Country')
                ?? $this->request->header('X-Geo-Country');

            if ($country !== null) {
                return is_array($country) ? $country[0] : $country;
            }
        }

        if ($this->user !== null) {
            $country = $this->getUserAttribute('country')
                ?? $this->getUserAttribute('country_code');

            if ($country !== null) {
                return (string) $country;
            }
        }

        return null;
    }

    public function getReferrer(): ?string
    {
        if (isset($this->metadata['referrer'])) {
            return (string) $this->metadata['referrer'];
        }

        if ($this->request !== null) {
            $referer = $this->request->header('Referer');
            if ($referer !== null) {
                return is_array($referer) ? $referer[0] : $referer;
            }
        }

        return null;
    }

    public function getCurrentTime(?string $timezone = null): Carbon
    {
        $tz = $timezone ?? $this->getTimezone();

        return Carbon::now($tz);
    }

    public function getTimezone(): string
    {
        if (isset($this->metadata['timezone'])) {
            return (string) $this->metadata['timezone'];
        }

        if ($this->user !== null) {
            $tz = $this->getUserAttribute('timezone');

            if ($tz !== null) {
                return (string) $tz;
            }
        }

        return config('app.timezone', 'UTC');
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if cart has metadata key.
     */
    public function hasCartMetadata(string $key): bool
    {
        if ($this->cart === null || ! method_exists($this->cart, 'hasMetadata')) {
            return false;
        }

        return $this->cart->hasMetadata($key);
    }

    /**
     * Get cart metadata value.
     */
    public function getCartMetadata(string $key, mixed $default = null): mixed
    {
        if ($this->cart === null || ! method_exists($this->cart, 'getMetadata')) {
            return $default;
        }

        return $this->cart->getMetadata($key) ?? $default;
    }

    /**
     * Get the currency code.
     */
    public function getCurrency(): string
    {
        if (isset($this->metadata['currency'])) {
            return (string) $this->metadata['currency'];
        }

        if ($this->cart !== null && method_exists($this->cart, 'getCurrency')) {
            return $this->cart->getCurrency();
        }

        return config('cart.money.default_currency', config('app.currency', 'USD'));
    }

    /**
     * Get cart items.
     *
     * @return Collection<int, mixed>
     */
    public function getCartItems(): Collection
    {
        if ($this->cart === null || ! method_exists($this->cart, 'getItems')) {
            return collect();
        }

        $items = $this->cart->getItems();

        return $items instanceof Collection ? $items : collect();
    }

    /**
     * Check if cart has a specific item.
     */
    public function hasCartItem(string $itemId): bool
    {
        if ($this->cart === null || ! method_exists($this->cart, 'has')) {
            return false;
        }

        return $this->cart->has($itemId);
    }

    // =========================================================================
    // NEW METHODS - Delegating to focused context classes
    // =========================================================================

    /**
     * Get the quantity of a specific product in the cart.
     */
    public function getProductQuantity(string $identifier): int
    {
        return $this->cartContext->getProductQuantity($identifier);
    }

    /**
     * Get user order count.
     */
    public function getOrderCount(): int
    {
        return $this->userContext->orderCount;
    }

    /**
     * Check if user has a specific segment.
     */
    public function hasUserSegment(string $segment): bool
    {
        return $this->userContext->hasSegment($segment);
    }

    /**
     * Check if user has any of the specified segments.
     */
    public function hasAnyUserSegment(array $segments): bool
    {
        return $this->userContext->hasAnySegment($segments);
    }

    /**
     * Get UTM source.
     */
    public function getUtmSource(): ?string
    {
        return $this->environmentContext->getUtmSource();
    }

    /**
     * Get UTM medium.
     */
    public function getUtmMedium(): ?string
    {
        return $this->environmentContext->getUtmMedium();
    }

    /**
     * Get UTM campaign.
     */
    public function getUtmCampaign(): ?string
    {
        return $this->environmentContext->getUtmCampaign();
    }

    /**
     * Check if request has UTM source.
     */
    public function hasUtmSource(): bool
    {
        return $this->environmentContext->hasUtmSource();
    }

    /**
     * Get the region (state/province).
     */
    public function getRegion(): ?string
    {
        return $this->environmentContext->region;
    }

    /**
     * Get the city.
     */
    public function getCity(): ?string
    {
        return $this->environmentContext->city;
    }

    /**
     * Check if request is from mobile device.
     */
    public function isMobile(): bool
    {
        return $this->environmentContext->isMobile();
    }

    /**
     * Check if request is from desktop.
     */
    public function isDesktop(): bool
    {
        return $this->environmentContext->isDesktop();
    }

    /**
     * Check if user is authenticated.
     */
    public function isAuthenticated(): bool
    {
        return $this->userContext->isAuthenticated();
    }

    /**
     * Check if user is a guest.
     */
    public function isGuest(): bool
    {
        return $this->userContext->isGuest();
    }

    /**
     * Get the payment method (if set in metadata).
     */
    public function getPaymentMethod(): ?string
    {
        return $this->metadata['payment_method'] ?? null;
    }

    /**
     * Get the coupon code (if set in metadata).
     */
    public function getCouponCode(): ?string
    {
        return $this->metadata['coupon_code']
            ?? $this->getCartMetadata('coupon_code');
    }

    /**
     * Get coupon usage count for current user/context.
     */
    public function getCouponUsageCount(string $couponCode): int
    {
        // Check metadata first
        $usageKey = "coupon_usage_{$couponCode}";
        if (isset($this->metadata[$usageKey])) {
            return (int) $this->metadata[$usageKey];
        }

        // Check cart metadata
        $cartUsage = $this->getCartMetadata($usageKey);
        if ($cartUsage !== null) {
            return (int) $cartUsage;
        }

        return 0;
    }
}
