<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('commerce-exchange-rates.base', 'USD');
        $this->migrator->add('commerce-exchange-rates.rates', []);
        $this->migrator->add('commerce-exchange-rates.history', []);
    }
};
