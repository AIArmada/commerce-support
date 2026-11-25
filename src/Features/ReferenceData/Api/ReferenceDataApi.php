<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\ReferenceData\Api;

interface ReferenceDataApi
{
    /** @return array{created:int, updated:int, skipped:int} */
    public function seedCurrencies(): array;

    /** @return array{created:int, updated:int, skipped:int} */
    public function seedLanguages(): array;

    /** @return array{created:int, updated:int, skipped:int} */
    public function seedTimezones(): array;

    /** @return array<string, string> */
    public function currencyIdsByCode(): array;

    /** @return array<string, string> */
    public function timezoneIdsByName(): array;

    /** @return array<string, string> */
    public function languageOptions(): array;

    public function connectionName(): ?string;
}
