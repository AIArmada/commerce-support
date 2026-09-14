<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

/**
 * Base signature validator for commerce webhooks.
 *
 * Provides common HMAC signature validation patterns.
 * Extend this class for package-specific signature validation.
 *
 * @example
 * ```php
 * class ChipSignatureValidator extends CommerceSignatureValidator
 * {
 *     protected function getSignatureHeader(): string
 *     {
 *         return 'X-Signature';
 *     }
 *
 *     protected function getHashAlgorithm(): string
 *     {
 *         return 'sha256';
 *     }
 * }
 * ```
 */
abstract class CommerceSignatureValidator implements SignatureValidator
{
    /**
     * Get the name of the header containing the signature.
     */
    abstract protected function getSignatureHeader(): string;

    /**
     * Validate the incoming request.
     */
    final public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = $this->getSignatureFromRequest($request);

        if (empty($signature)) {
            return false;
        }

        $secret = $config->signingSecret;

        if (empty($secret)) {
            return false;
        }

        if (! $this->isTimestampFresh($request)) {
            return false;
        }

        return $this->validateSignature($request, $signature, $secret);
    }

    /**
     * Get the signature from the request.
     */
    protected function getSignatureFromRequest(Request $request): ?string
    {
        return $request->header($this->getSignatureHeader());
    }

    /**
     * Validate the signature against the payload.
     */
    protected function validateSignature(Request $request, string $signature, string $secret): bool
    {
        $payload = $this->getPayloadForSigning($request);
        $expectedSignature = $this->computeSignature($payload, $secret);

        return hash_equals($expectedSignature, $this->stripSchemePrefix($signature));
    }

    /**
     * Strip a `{algo}=` scheme prefix (e.g. `sha256=`) that providers such
     * as GitHub prepend to hex digests.
     */
    protected function stripSchemePrefix(string $signature): string
    {
        $prefix = mb_strtolower($this->getHashAlgorithm()) . '=';

        if (str_starts_with(mb_strtolower($signature), $prefix)) {
            $stripped = mb_substr($signature, mb_strlen($prefix));

            if ($stripped !== '') {
                return $stripped;
            }
        }

        return $signature;
    }

    /**
     * Header carrying the provider's Unix signing timestamp, or null to
     * skip freshness checks. Override to opt into replay-window enforcement.
     */
    protected function getTimestampHeader(): ?string
    {
        return null;
    }

    /**
     * Maximum age (and future clock-skew leeway), in seconds, for the
     * provider signing timestamp.
     */
    protected function getTimestampToleranceSeconds(): int
    {
        return 300;
    }

    private function isTimestampFresh(Request $request): bool
    {
        $header = $this->getTimestampHeader();

        if ($header === null) {
            return true;
        }

        $value = $request->header($header);

        if ($value === null || ! is_numeric($value)) {
            return false;
        }

        $timestamp = (int) $value;
        $now = time();
        $tolerance = $this->getTimestampToleranceSeconds();

        return $timestamp >= $now - $tolerance && $timestamp <= $now + $tolerance;
    }

    /**
     * Get the payload to use for signature computation.
     */
    protected function getPayloadForSigning(Request $request): string
    {
        return $request->getContent();
    }

    /**
     * Compute the expected signature.
     */
    protected function computeSignature(string $payload, string $secret): string
    {
        return hash_hmac($this->getHashAlgorithm(), $payload, $secret);
    }

    /**
     * Get the hash algorithm to use.
     */
    protected function getHashAlgorithm(): string
    {
        return 'sha256';
    }
}
