<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\SeedTimezonesAction;
use Illuminate\Console\Command;

class SeedTimezonesCommand extends Command
{
    protected $signature = 'commerce:seed-timezones';

    protected $description = 'Seed the shared timezone reference table';

    public function handle(SeedTimezonesAction $action): int
    {
        $result = $action->execute();

        $this->info(sprintf('Timezones seeded: %d created, %d updated, %d skipped.', $result['created'], $result['updated'], $result['skipped']));

        return self::SUCCESS;
    }
}
