<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

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
        $content = File::get($envPath);
        $lines = explode("\n", $content);
        $existingKeys = [];

        foreach ($lines as $index => $line) {
            foreach ($updates as $key => $value) {
                if (str_starts_with(mb_trim($line), $key . '=')) {
                    $existingKeys[$key] = $index;

                    if (! $force) {
                        $warn("Skipping {$key} (already exists, use --force to overwrite)");
                        unset($updates[$key]);
                    }
                }
            }
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

        File::put($envPath, implode("\n", $lines));
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
