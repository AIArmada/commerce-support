<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\Buyable;
use InvalidArgumentException;

/**
 * Immutable, typed snapshot of a sellable line.
 */
final readonly class BuyableLineData
{
    public const string ATTRIBUTE_SKU = 'sku';

    public const string ATTRIBUTE_WEIGHT = 'weight';

    public const string ATTRIBUTE_DIMENSIONS = 'dimensions';

    public const string ATTRIBUTE_REQUIRES_SHIPPING = 'requires_shipping';

    public const string ATTRIBUTE_IS_TAXABLE = 'is_taxable';

    public const string ATTRIBUTE_TAX_CLASS = 'tax_class';

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{length: int, width: int, height: int}|null  $dimensionsCm
     */
    public function __construct(
        public string $identifier,
        public ?string $catalogIdentifier,
        public string $name,
        public int $unitPrice,
        public string $currency,
        public int $quantity,
        public array $attributes,
        public ?string $sku,
        public bool $requiresShipping,
        public bool $isTaxable,
        public ?string $taxClass,
        public ?int $weightGrams,
        public ?array $dimensionsCm,
    ) {}

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    public static function fromBuyable(Buyable $buyable, int $quantity = 1, array $extraAttributes = []): self
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A buyable line quantity must be at least one.');
        }

        $identifier = mb_trim($buyable->getBuyableIdentifier());
        $name = mb_trim($buyable->getBuyableName());
        $currency = mb_strtoupper(mb_trim($buyable->getBuyableCurrency()));

        if ($identifier === '') {
            throw new InvalidArgumentException('A buyable line requires a non-empty identifier.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('A buyable line requires a non-empty name.');
        }

        if ($currency === '') {
            throw new InvalidArgumentException('A buyable line requires an explicit currency.');
        }

        $attributes = array_merge($buyable->getBuyableAttributes(), $extraAttributes);
        $dimensions = self::normalizeDimensions($attributes[self::ATTRIBUTE_DIMENSIONS] ?? null);
        $weight = $attributes[self::ATTRIBUTE_WEIGHT] ?? null;
        $sku = $attributes[self::ATTRIBUTE_SKU] ?? null;
        $taxClass = $attributes[self::ATTRIBUTE_TAX_CLASS] ?? null;

        return new self(
            identifier: $identifier,
            catalogIdentifier: $buyable->getBuyableCatalogIdentifier(),
            name: $name,
            unitPrice: $buyable->getBuyablePrice(),
            currency: $currency,
            quantity: $quantity,
            attributes: $attributes,
            sku: is_string($sku) && mb_trim($sku) !== '' ? mb_trim($sku) : null,
            requiresShipping: (bool) ($attributes[self::ATTRIBUTE_REQUIRES_SHIPPING] ?? true),
            isTaxable: (bool) ($attributes[self::ATTRIBUTE_IS_TAXABLE] ?? true),
            taxClass: is_string($taxClass) && mb_trim($taxClass) !== '' ? mb_trim($taxClass) : null,
            weightGrams: is_numeric($weight) && (int) $weight > 0 ? (int) $weight : null,
            dimensionsCm: $dimensions,
        );
    }

    /**
     * @return array{length: int, width: int, height: int}|null
     */
    private static function normalizeDimensions(mixed $dimensions): ?array
    {
        if (! is_array($dimensions)) {
            return null;
        }

        $length = $dimensions['length'] ?? null;
        $width = $dimensions['width'] ?? null;
        $height = $dimensions['height'] ?? null;

        if (! is_numeric($length) || ! is_numeric($width) || ! is_numeric($height)) {
            return null;
        }

        $normalized = [
            'length' => (int) $length,
            'width' => (int) $width,
            'height' => (int) $height,
        ];

        if ($normalized['length'] <= 0 || $normalized['width'] <= 0 || $normalized['height'] <= 0) {
            return null;
        }

        return $normalized;
    }
}
