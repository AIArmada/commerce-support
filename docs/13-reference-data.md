---
title: Reference Data
---

# Reference Data

Commerce Support provides a bundled ISO 639-1 language lookup table with seeding infrastructure.

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
