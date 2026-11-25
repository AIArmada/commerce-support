---
title: Reference Data
---

# Reference Data

Commerce Support provides the shared language, currency, and timezone reference catalogues used across Commerce packages.

## Languages

A `languages` table is available with `code` (ISO 639-1), `name`, `native` (endonym), and `dir` (text direction).

Seed it once after migrating:

```bash
php artisan db:seed --class="AIArmada\CommerceSupport\Database\Seeders\LanguageSeeder"
```

Or from a seeder:

```php
$this->call(\AIArmada\CommerceSupport\Database\Seeders\LanguageSeeder::class);
```

## Currencies

The `currencies` table contains shared ISO 4217 currency metadata, including symbols and display precision.

```bash
php artisan db:seed --class="AIArmada\CommerceSupport\Database\Seeders\CurrencySeeder"
```

## Timezones

The `timezones` table contains shared IANA timezone identifiers.

```bash
php artisan db:seed --class="AIArmada\CommerceSupport\Database\Seeders\TimezoneSeeder"
```

## Package integration

Business consumers should inject `AIArmada\CommerceSupport\Features\ReferenceData\Api\ReferenceDataApi` for seeding, code-to-ID maps and language options. The API returns scalar values and arrays, not Eloquent builders or models. Filament resources and Eloquent relationships may bind the reference models in `Features/ReferenceData/Models` at their framework integration boundary.
