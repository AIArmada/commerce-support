<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\ReferenceData\Queries;

use AIArmada\CommerceSupport\Features\ReferenceData\Actions\SeedCurrenciesAction;
use AIArmada\CommerceSupport\Features\ReferenceData\Actions\SeedLanguagesAction;
use AIArmada\CommerceSupport\Features\ReferenceData\Actions\SeedTimezonesAction;
use AIArmada\CommerceSupport\Features\ReferenceData\Api\ReferenceDataApi;
use AIArmada\CommerceSupport\Features\ReferenceData\Models\Currency;
use AIArmada\CommerceSupport\Features\ReferenceData\Models\Language;
use AIArmada\CommerceSupport\Features\ReferenceData\Models\Timezone;
use LogicException;

final readonly class EloquentReferenceDataApi implements ReferenceDataApi
{
    public function __construct(
        private SeedCurrenciesAction $currencies,
        private SeedLanguagesAction $languages,
        private SeedTimezonesAction $timezones,
    ) {}

    public function seedCurrencies(): array
    {
        return $this->currencies->execute();
    }

    public function seedLanguages(): array
    {
        return $this->languages->execute();
    }

    public function seedTimezones(): array
    {
        return $this->timezones->execute();
    }

    public function currencyIdsByCode(): array
    {
        return Currency::query()->pluck('id', 'code')->all();
    }

    public function timezoneIdsByName(): array
    {
        return Timezone::query()->pluck('id', 'name')->all();
    }

    public function languageOptions(): array
    {
        return Language::query()->orderBy('name')->pluck('name', 'code')->all();
    }

    public function connectionName(): ?string
    {
        $connection = (new Currency)->getConnection()->getName();

        if ((new Timezone)->getConnection()->getName() !== $connection || (new Language)->getConnection()->getName() !== $connection) {
            throw new LogicException('Commerce reference models must use the same database connection.');
        }

        return $connection;
    }
}
