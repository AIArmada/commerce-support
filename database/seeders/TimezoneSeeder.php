<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Database\Seeders;

use AIArmada\CommerceSupport\Actions\SeedTimezonesAction;
use Illuminate\Database\Seeder;

final class TimezoneSeeder extends Seeder
{
    public function run(): void
    {
        app(SeedTimezonesAction::class)->execute();
    }
}
