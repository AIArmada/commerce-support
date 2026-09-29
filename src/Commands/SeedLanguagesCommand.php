<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\SeedLanguagesAction;
use Illuminate\Console\Command;

class SeedLanguagesCommand extends Command
{
    protected $signature = 'commerce:seed-languages';

    protected $description = 'Seed languages table with bundled ISO 639-1 language data';

    public function handle(SeedLanguagesAction $action): int
    {
        $result = $action->execute();

        $this->info(sprintf(
            'Languages seeded: %d created, %d updated, %d skipped.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
