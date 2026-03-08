<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Context;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Environment-specific context for targeting evaluation.
 *
 * Encapsulates all environment/request data used in targeting rules:
 * - Channel (web, mobile, api, pos)
 * - Device type (desktop, mobile, tablet)
 * - Geographic data (country, region, city)
 * - Time and timezone
 * - Referrer and UTM parameters
 */
readonly class EnvironmentContext
{
    /**
     * @param  string  $channel  Sales channel
     * @param  string  $device  Device type
     * @param  string|null  $country  ISO country code
     * @param  string|null  $region  State/province
     * @param  string|null  $city  City name
     * @param  string  $timezone  Timezone identifier
     * @param  string|null  $referrer  Referrer URL
     * @param  string  $currency  ISO currency code
     * @param  string|null  $locale  User locale
     * @param  array<string, string|null>  $utm  UTM parameters
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public string $channel = 'web',
        public string $device = 'desktop',
        public ?string $country = null,
        public ?string $region = null,
        public ?string $city = null,
        public string $timezone = 'UTC',
        public ?string $referrer = null,
        public string $currency = 'USD',
        public ?string $locale = null,
        public array $utm = [],
        public array $metadata = [],
    ) {}

    /**
     * Create context from HTTP request.
     */
    public static function fromRequest(?Request $request, array $metadata = []): self
    {
        if ($request === null) {
            return new self(metadata: $metadata);
        }

        return new self(
            channel: self::extractChannel($request, $metadata),
            device: self::extractDevice($request, $metadata),
            country: self::extractCountry($request, $metadata),
            region: self::extractRegion($request, $metadata),
            city: self::extractCity($request, $metadata),
            timezone: self::extractTimezone($request, $metadata),
            referrer: self::extractReferrer($request, $metadata),
            currency: $metadata['currency'] ?? config('app.currency', 'USD'),
            locale: $request->getPreferredLanguage() ?? config('app.locale', 'en'),
            utm: self::extractUtm($request),
            metadata: $metadata,
        );
    }

    public function getCurrentTime(): Carbon
    {
        return Carbon::now($this->timezone);
    }

    public function isChannel(string $channel): bool
    {
        return $this->channel === $channel;
    }

    public function isDevice(string $device): bool
    {
        return $this->device === $device;
    }

    public function isMobile(): bool
    {
        return $this->device === 'mobile';
    }

    public function isDesktop(): bool
    {
        return $this->device === 'desktop';
    }

    public function isTablet(): bool
    {
        return $this->device === 'tablet';
    }

    public function isFromCountry(string $country): bool
    {
        return $this->country !== null
            && mb_strtoupper($this->country) === mb_strtoupper($country);
    }

    public function isFromAnyCountry(array $countries): bool
    {
        if ($this->country === null) {
            return false;
        }

        $upperCountry = mb_strtoupper($this->country);

        return in_array($upperCountry, array_map('strtoupper', $countries), true);
    }

    public function hasUtmSource(): bool
    {
        return ! empty($this->utm['source']);
    }

    public function getUtmSource(): ?string
    {
        return $this->utm['source'] ?? null;
    }

    public function getUtmMedium(): ?string
    {
        return $this->utm['medium'] ?? null;
    }

    public function getUtmCampaign(): ?string
    {
        return $this->utm['campaign'] ?? null;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    private static function extractChannel(Request $request, array $metadata): string
    {
        if (isset($metadata['channel'])) {
            return (string) $metadata['channel'];
        }

        $channel = $request->header('X-Channel')
            ?? $request->header('X-Sales-Channel');

        if ($channel !== null) {
            return is_array($channel) ? $channel[0] : $channel;
        }

        return 'web';
    }

    private static function extractDevice(Request $request, array $metadata): string
    {
        if (isset($metadata['device'])) {
            return (string) $metadata['device'];
        }

        $userAgent = $request->userAgent() ?? '';

        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile|wpdesktop/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function extractCountry(Request $request, array $metadata): ?string
    {
        if (isset($metadata['country'])) {
            return (string) $metadata['country'];
        }

        $country = $request->header('CF-IPCountry')
            ?? $request->header('X-Country')
            ?? $request->header('X-Geo-Country');

        if ($country !== null) {
            return is_array($country) ? $country[0] : $country;
        }

        return null;
    }

    private static function extractRegion(Request $request, array $metadata): ?string
    {
        if (isset($metadata['region'])) {
            return (string) $metadata['region'];
        }

        $region = $request->header('CF-Region')
            ?? $request->header('X-Region')
            ?? $request->header('X-Geo-Region');

        if ($region !== null) {
            return is_array($region) ? $region[0] : $region;
        }

        return null;
    }

    private static function extractCity(Request $request, array $metadata): ?string
    {
        if (isset($metadata['city'])) {
            return (string) $metadata['city'];
        }

        $city = $request->header('CF-IPCity')
            ?? $request->header('X-City')
            ?? $request->header('X-Geo-City');

        if ($city !== null) {
            return is_array($city) ? $city[0] : $city;
        }

        return null;
    }

    private static function extractTimezone(Request $request, array $metadata): string
    {
        if (isset($metadata['timezone'])) {
            return (string) $metadata['timezone'];
        }

        $timezone = $request->header('X-Timezone');
        if ($timezone !== null) {
            return is_array($timezone) ? $timezone[0] : $timezone;
        }

        return config('app.timezone', 'UTC');
    }

    private static function extractReferrer(Request $request, array $metadata): ?string
    {
        if (isset($metadata['referrer'])) {
            return (string) $metadata['referrer'];
        }

        $referer = $request->header('Referer');
        if ($referer !== null) {
            return is_array($referer) ? $referer[0] : $referer;
        }

        return null;
    }

    /**
     * @return array<string, string|null>
     */
    private static function extractUtm(Request $request): array
    {
        return [
            'source' => $request->query('utm_source'),
            'medium' => $request->query('utm_medium'),
            'campaign' => $request->query('utm_campaign'),
            'term' => $request->query('utm_term'),
            'content' => $request->query('utm_content'),
        ];
    }
}
