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

        return hash_equals($expectedSignature, $signature);
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
