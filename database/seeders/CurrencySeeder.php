<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Database\Seeders;

use AIArmada\CommerceSupport\Actions\SeedCurrenciesAction;
use Illuminate\Database\Seeder;

final class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        app(SeedCurrenciesAction::class)->execute();
    }
}
