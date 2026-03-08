<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Targeting\Evaluators;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingContextInterface;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingRuleEvaluator;

/**
 * Evaluates targeting rules based on referral/attribution source.
 *
 * Checks UTM parameters, referrer URL, or custom attribution data.
 *
 * @example
 * ```php
 * // Only for Google Ads traffic
 * ['type' => 'referral_source', 'utm_source' => 'google', 'utm_medium' => 'cpc']
 *
 * // From specific campaign
 * ['type' => 'referral_source', 'utm_campaign' => 'black_friday_2024']
 *
 * // From referrer domain
 * ['type' => 'referral_source', 'referrer_domain' => 'instagram.com']
 *
 * // Affiliate traffic
 * ['type' => 'referral_source', 'sources' => ['affiliate', 'partner']]
 * ```
 */
final readonly class ReferralSourceEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === $this->getType();
    }

    public function getType(): string
    {
        return 'referral_source';
    }

    public function evaluate(array $rule, TargetingContextInterface $context): bool
    {
        // Check UTM source
        if (isset($rule['utm_source'])) {
            $utmSource = $this->getUtmSource($context);
            if (! $this->matchesValue($utmSource, $rule['utm_source'])) {
                return false;
            }
        }

        // Check UTM medium
        if (isset($rule['utm_medium'])) {
            $utmMedium = $this->getUtmMedium($context);
            if (! $this->matchesValue($utmMedium, $rule['utm_medium'])) {
                return false;
            }
        }

        // Check UTM campaign
        if (isset($rule['utm_campaign'])) {
            $utmCampaign = $this->getUtmCampaign($context);
            if (! $this->matchesValue($utmCampaign, $rule['utm_campaign'])) {
                return false;
            }
        }

        // Check referrer domain
        if (isset($rule['referrer_domain'])) {
            $referrer = $this->getReferrer($context);
            if (! $this->matchesReferrerDomain($referrer, $rule['referrer_domain'])) {
                return false;
            }
        }

        // Check generic sources (from metadata)
        if (isset($rule['sources'])) {
            $source = $this->getSource($context);
            $allowedSources = array_map('strtolower', (array) $rule['sources']);
            if ($source === null || ! in_array(mb_strtolower($source), $allowedSources, true)) {
                return false;
            }
        }

        // Check exclusions
        if (isset($rule['exclude_sources'])) {
            $source = $this->getSource($context);
            $excludeSources = array_map('strtolower', (array) $rule['exclude_sources']);
            if ($source !== null && in_array(mb_strtolower($source), $excludeSources, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string>
     */
    public function validate(array $rule): array
    {
        $validKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'referrer_domain', 'sources', 'exclude_sources'];
        $hasCondition = false;

        foreach ($validKeys as $key) {
            if (isset($rule[$key])) {
                $hasCondition = true;

                break;
            }
        }

        if (! $hasCondition) {
            return ['Rule must have at least one condition: ' . implode(', ', $validKeys)];
        }

        return [];
    }

    private function matchesValue(?string $actual, string | array $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        $actual = mb_strtolower($actual);

        if (is_array($expected)) {
            return in_array($actual, array_map('strtolower', $expected), true);
        }

        return $actual === mb_strtolower($expected);
    }

    private function matchesReferrerDomain(?string $referrer, string | array $domains): bool
    {
        if ($referrer === null) {
            return false;
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }

        $host = mb_strtolower($host);
        $domains = is_array($domains) ? $domains : [$domains];

        foreach ($domains as $domain) {
            $domain = mb_strtolower($domain);
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    private function getUtmSource(TargetingContextInterface $context): ?string
    {
        if (method_exists($context, 'getUtmSource')) {
            return $context->getUtmSource();
        }

        if (method_exists($context, 'getMetadata')) {
            return $context->getMetadata('utm_source');
        }

        return null;
    }

    private function getUtmMedium(TargetingContextInterface $context): ?string
    {
        if (method_exists($context, 'getUtmMedium')) {
            return $context->getUtmMedium();
        }

        if (method_exists($context, 'getMetadata')) {
            return $context->getMetadata('utm_medium');
        }

        return null;
    }

    private function getUtmCampaign(TargetingContextInterface $context): ?string
    {
        if (method_exists($context, 'getUtmCampaign')) {
            return $context->getUtmCampaign();
        }

        if (method_exists($context, 'getMetadata')) {
            return $context->getMetadata('utm_campaign');
        }

        return null;
    }

    private function getReferrer(TargetingContextInterface $context): ?string
    {
        if (method_exists($context, 'getReferrer')) {
            return $context->getReferrer();
        }

        return null;
    }

    private function getSource(TargetingContextInterface $context): ?string
    {
        // Try UTM source first
        $utmSource = $this->getUtmSource($context);
        if ($utmSource !== null) {
            return $utmSource;
        }

        // Check metadata for generic source
        if (method_exists($context, 'getMetadata')) {
            return $context->getMetadata('source')
                ?? $context->getMetadata('traffic_source')
                ?? $context->getMetadata('referral_source');
        }

        return null;
    }
}
