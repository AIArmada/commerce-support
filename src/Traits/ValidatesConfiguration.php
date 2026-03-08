<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use RuntimeException;

trait ValidatesConfiguration // @phpstan-ignore trait.unused
{
    /**
     * Validate that required configuration keys exist.
     *
     * @param  string  $configFile  The config file name (without .php)
     * @param  array<string>  $requiredKeys  Array of required config keys in dot notation
     *
     * @throws RuntimeException
     */
    protected function validateConfiguration(string $configFile, array $requiredKeys): void
    {
        // Skip validation during testing unless explicitly enabled
        if (! app()->environment('production')) {
            // Only validate if explicitly enabled via config
            if (! config("{$configFile}.validate_config", false)) {
                return;
            }
        }

        foreach ($requiredKeys as $key) {
            $fullKey = "{$configFile}.{$key}";
            $value = config($fullKey);

            if ($value === null) {
                throw new RuntimeException(
                    "Required configuration key [{$fullKey}] is not set. " .
                    "Please publish the configuration file with: php artisan vendor:publish --tag={$configFile}-config"
                );
            }
        }
    }
}
