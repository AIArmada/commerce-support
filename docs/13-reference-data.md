---
title: Reference Data
---

# Reference Data

Commerce Support provides the shared language, currency, and timezone reference catalogues used across Commerce packages.

## Languages

A `languages` table is available with `code` (ISO 639-1), `name`, `native` (endonym), and `dir` (text direction).

Seed it once after migrating:

```bash
php artisan commerce:seed-languages
```

Or from a seeder:

```php
$this->call(\AIArmada\CommerceSupport\Database\Seeders\LanguageSeeder::class);
```

## Currencies

The `currencies` table contains shared ISO 4217 currency metadata, including symbols and display precision.

```bash
php artisan commerce:seed-currencies
```

## Timezones

The `timezones` table contains shared IANA timezone identifiers.

```bash
php artisan commerce:seed-timezones
```
