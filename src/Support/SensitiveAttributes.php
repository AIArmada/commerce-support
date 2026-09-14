<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

/**
 * Shared sensitive-attribute list for activity logging and auditing.
 *
 * Attributes matching this list are excluded from default loggable
 * attributes and redacted from audit payloads. Matching is exact on the
 * full attribute name, plus substring matching on unambiguous credential
 * fragments (password/secret/token/...). Models with explicit per-model
 * allowlists are unaffected.
 */
final class SensitiveAttributes
{
    /**
     * @var array<int, string>
     */
    private const EXACT = [
        'password',
        'password_hash',
        'password_confirmation',
        'remember_token',
        'api_key',
        'api_secret',
        'access_key',
        'private_key',
        'secret',
        'credit_card',
        'card_number',
        'card_expiry',
        'cvv',
        'cvc',
        'ssn',
        'sin',
        'tax_id',
        'vat_number',
        'bank_account',
        'account_number',
        'iban',
        'routing_number',
        'passport',
        'passport_number',
        'national_id',
        'email',
        'phone',
        'phone_number',
        'mobile',
        'mobile_number',
        'address',
        'address_line_1',
        'address_line_2',
        'street',
        'street_address',
        'city',
        'state',
        'province',
        'postcode',
        'postal_code',
        'zip',
        'zip_code',
        'country',
        'country_code',
        'name',
        'first_name',
        'last_name',
        'full_name',
        'display_name',
        'dob',
        'date_of_birth',
        'birthdate',
        'birth_date',
        'pin',
        'passcode',
        'authorization',
    ];

    /**
     * @var array<int, string>
     */
    private const FRAGMENTS = [
        'password',
        'passwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ];

    /**
     * @return array<int, string>
     */
    public static function list(): array
    {
        return self::EXACT;
    }

    public static function matches(string $attribute): bool
    {
        if (in_array($attribute, self::EXACT, true)) {
            return true;
        }

        $lower = mb_strtolower($attribute);

        foreach (self::FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $attributes
     * @return array<int, string>
     */
    public static function exclude(array $attributes): array
    {
        return array_values(array_filter(
            $attributes,
            fn (string $attribute): bool => ! self::matches($attribute),
        ));
    }
}
