<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use InvalidArgumentException;

/**
 * Immutable application-defined purchase selection.
 *
 * Associative keys are sorted recursively to give equivalent selections the
 * same fingerprint; list order is meaningful. Values are JSON-safe primitives
 * and arrays, not domain objects or floating-point quantities.
 */
final readonly class PurchaseContext
{
    public const string ATTRIBUTE_CONTEXT = 'purchase_context';

    public const string ATTRIBUTE_FINGERPRINT = 'purchase_context_fingerprint';

    /** @var array<string, mixed> */
    public array $values;

    public string $fingerprint;

    /** @param array<string, mixed> $values */
    public function __construct(array $values)
    {
        $this->values = self::canonical($values);
        $this->fingerprint = hash('sha256', json_encode($this->values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromAttributes(array $attributes): ?self
    {
        $hasContext = array_key_exists(self::ATTRIBUTE_CONTEXT, $attributes);
        $hasFingerprint = array_key_exists(self::ATTRIBUTE_FINGERPRINT, $attributes);

        if (! $hasContext && ! $hasFingerprint) {
            return null;
        }

        $values = $attributes[self::ATTRIBUTE_CONTEXT] ?? null;
        $fingerprint = $attributes[self::ATTRIBUTE_FINGERPRINT] ?? null;

        if (! is_array($values) || ! is_string($fingerprint)) {
            throw new InvalidArgumentException('Purchase context and fingerprint must both be present.');
        }

        $context = new self($values);

        if (! hash_equals($context->fingerprint, $fingerprint)) {
            throw new InvalidArgumentException('Purchase context fingerprint does not match the selection.');
        }

        return $context;
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            if ($value === null || is_string($value) || is_bool($value) || is_int($value)) {
                return $value;
            }

            throw new InvalidArgumentException('Purchase context only accepts JSON-safe strings, integers, booleans, null and arrays.');
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $nested) {
            $value[$key] = self::canonical($nested);
        }

        return $value;
    }
}
