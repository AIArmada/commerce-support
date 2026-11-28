<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Foundation\Application;

final class SettingsMigrationPath
{
    public static function register(Application $app, string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $append = static function () use ($app, $path): void {
            $paths = $app['config']->get('settings.migrations_paths', []);

            if (! is_array($paths)) {
                $paths = [];
            }

            if (in_array($path, $paths, true)) {
                return;
            }

            $paths[] = $path;

            $app['config']->set('settings.migrations_paths', $paths);
        };

        if ($app->isBooted()) {
            $append();

            return;
        }

        $app->booting($append);
    }
}
