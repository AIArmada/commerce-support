<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\ReferenceData;

use AIArmada\CommerceSupport\Features\ReferenceData\Api\ReferenceDataApi;
use AIArmada\CommerceSupport\Features\ReferenceData\Queries\EloquentReferenceDataApi;
use AIArmada\CommerceSupport\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ReferenceDataApi::class, EloquentReferenceDataApi::class);
    }

    public function boot(Application $app): void {}
}
