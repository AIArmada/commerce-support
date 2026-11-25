<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Database\Seeders;

use AIArmada\CommerceSupport\Features\ReferenceData\Api\ReferenceDataApi;
use Illuminate\Database\Seeder;

final class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        app(ReferenceDataApi::class)->seedCurrencies();
    }
}
