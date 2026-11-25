<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\SignedDownloads;

use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use AIArmada\CommerceSupport\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(SignedDownloadApi::class, SignedDownloadService::class);
    }

    public function boot(Application $app): void {}
}
