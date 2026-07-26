<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\SeedCurrenciesAction;
use Illuminate\Console\Command;

class SeedCurrenciesCommand extends Command
{
    protected $signature = 'commerce:seed-currencies';

    protected $description = 'Seed the shared currency reference table';

    public function handle(SeedCurrenciesAction $action): int
    {
        $result = $action->execute();

        $this->info(sprintf('Currencies seeded: %d created, %d updated, %d skipped.', $result['created'], $result['updated'], $result['skipped']));

        return self::SUCCESS;
    }
}
