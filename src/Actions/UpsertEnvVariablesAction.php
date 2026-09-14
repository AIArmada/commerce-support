<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class UpsertEnvVariablesAction
{
    use AsAction;

    /**
     * @param  array<string, string>  $updates
     * @param  callable(string): void  $warn
     * @param  callable(string): void  $info
     */
    public function handle(array $updates, bool $force, callable $warn, callable $info): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            throw new RuntimeException("Cannot upsert environment variables: {$envPath} does not exist.");
        }

        $lock = fopen($envPath, 'r');

        if ($lock === false) {
            throw new RuntimeException("Cannot lock {$envPath} for environment update.");
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException("Cannot acquire exclusive lock on {$envPath}.");
            }

            $this->applyUpdates($envPath, $updates, $force, $warn, $info);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param  array<string, string>  $updates
     * @param  callable(string): void  $warn
     * @param  callable(string): void  $info
     */
    private function applyUpdates(string $envPath, array $updates, bool $force, callable $warn, callable $info): void
    {
        $lines = explode("\n", File::get($envPath));
        $existingKeys = [];

        foreach ($lines as $index => $line) {
            $lineKey = self::lineKey($line);

            if ($lineKey === null || ! array_key_exists($lineKey, $updates)) {
                continue;
            }

            $existingKeys[$lineKey] = $index;

            if (! $force) {
                $warn("Skipping {$lineKey} (already exists, use --force to overwrite)");
                unset($updates[$lineKey]);
            }
        }

        if ($updates === []) {
            return;
        }

        foreach ($updates as $key => $value) {
            $envLine = $key . '=' . $this->formatEnvValue($value);

            if (isset($existingKeys[$key])) {
                $lines[$existingKeys[$key]] = $envLine;
                $info("Updated {$key}");
            } else {
                $lines[] = $envLine;
                $info("Added {$key}");
            }
        }

        // Atomic same-directory replace: readers never see a partial file.
        $temporaryPath = $envPath . '.tmp';

        File::put($temporaryPath, implode("\n", $lines));

        if (! rename($temporaryPath, $envPath)) {
            throw new RuntimeException("Cannot replace {$envPath} with updated environment.");
        }
    }

    /**
     * Extract the exact variable name from an .env line, tolerating
     * whitespace around the equals sign. Returns null for blank lines,
     * comments, and non-assignment lines.
     */
    private static function lineKey(string $line): ?string
    {
        $trimmed = mb_trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return null;
        }

        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=/', $trimmed, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function formatEnvValue(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);

        $escaped = str_replace('\\', '\\\\', $value);
        $escaped = str_replace('"', '\\"', $escaped);
        $escaped = str_replace('$', '\\$', $escaped);

        return '"' . $escaped . '"';
    }
}
