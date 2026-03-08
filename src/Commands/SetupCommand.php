<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Commerce Setup Command
 *
 * Interactive setup wizard for configuring Commerce packages.
 * Prompts users for environment variables and writes to .env file.
 */
final class SetupCommand extends Command
{
    protected $signature = 'commerce:setup
                          {--force : Overwrite existing environment variables}';

    protected $description = 'Configure Commerce environment variables interactively';

    public function handle(): int
    {
        info('AIArmada Commerce Setup Wizard');

        $this->components->info('This wizard will help you configure Commerce packages.');
        $this->newLine();

        if (! File::exists(base_path('.env'))) {
            $this->components->error('No .env file found. Please create one first.');

            return self::FAILURE;
        }

        $updates = [];

        // CHIP Payment Gateway
        if (confirm('Configure CHIP payment gateway?', default: false)) {
            $brandId = text(
                label: 'CHIP Brand ID',
                placeholder: 'your-brand-id',
                required: false
            );
            if ($brandId) {
                $updates['CHIP_BRAND_ID'] = $brandId;
            }

            $secretKey = password(
                label: 'CHIP Secret Key',
                required: false
            );
            if ($secretKey) {
                $updates['CHIP_SECRET_KEY'] = $secretKey;
            }

            $webhookUrl = text(
                label: 'CHIP Webhook URL',
                placeholder: 'https://your-domain.com/webhooks/chip',
                required: false
            );
            if ($webhookUrl) {
                $updates['CHIP_WEBHOOK_URL'] = $webhookUrl;
            }

            if (confirm('Set CHIP mode?', default: false)) {
                $updates['CHIP_MODE'] = confirm('Use production mode?', default: false) ? 'production' : 'sandbox';
            }
        }

        // J&T Express
        if (confirm('Configure J&T Express shipping?', default: false)) {
            $apiKey = password(
                label: 'J&T Express API Key',
                required: false
            );
            if ($apiKey) {
                $updates['JNT_API_KEY'] = $apiKey;
            }

            $apiUrl = text(
                label: 'J&T Express API URL',
                default: 'https://api.jtexpress.com.my',
                required: false
            );
            if ($apiUrl && $apiUrl !== 'https://api.jtexpress.com.my') {
                $updates['JNT_API_URL'] = $apiUrl;
            }

            $customerCode = text(
                label: 'J&T Express Customer Code',
                required: false
            );
            if ($customerCode) {
                $updates['JNT_CUSTOMER_CODE'] = $customerCode;
            }
        }

        // Database Configuration
        if (confirm('Configure Commerce database settings?', default: false)) {
            $isPostgres = confirm(
                label: 'Are you using PostgreSQL?',
                default: false
            );

            if ($isPostgres) {
                $useJsonb = confirm(
                    label: 'Use JSONB instead of JSON?',
                    default: true,
                    hint: 'JSONB offers better performance and indexing capabilities'
                );

                $updates['COMMERCE_JSON_COLUMN_TYPE'] = $useJsonb ? 'jsonb' : 'json';
            } else {
                $updates['COMMERCE_JSON_COLUMN_TYPE'] = 'json';
            }
        }

        if (empty($updates)) {
            warning('No configuration changes made.');

            return self::SUCCESS;
        }

        $this->updateEnvFile($updates);

        $this->newLine();
        $this->components->info('Commerce configuration completed successfully!');
        $this->components->info('Remember to run: php artisan migrate');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Update .env file with new values
     *
     * @param  array<string, string>  $updates
     */
    private function updateEnvFile(array $updates): void
    {
        $envPath = base_path('.env');
        $content = File::get($envPath);
        $lines = explode("\n", $content);
        $existingKeys = [];

        // Find existing keys
        foreach ($lines as $index => $line) {
            foreach ($updates as $key => $value) {
                if (str_starts_with(mb_trim($line), $key . '=')) {
                    $existingKeys[$key] = $index;

                    if (! $this->option('force')) {
                        $this->components->warn("Skipping {$key} (already exists, use --force to overwrite)");
                        unset($updates[$key]);
                    }
                }
            }
        }

        // Update existing or append new
        foreach ($updates as $key => $value) {
            $envLine = $key . '=' . $this->formatEnvValue($value);

            if (isset($existingKeys[$key])) {
                // Update existing line
                $lines[$existingKeys[$key]] = $envLine;
                $this->components->info("Updated {$key}");
            } else {
                // Append new line
                $lines[] = $envLine;
                $this->components->info("Added {$key}");
            }
        }

        File::put($envPath, implode("\n", $lines));
    }

    private function formatEnvValue(string $value): string
    {
        // Never allow multi-line .env injection.
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);

        // Always quote to avoid parsing surprises and variable expansion.
        $escaped = str_replace('\\', '\\\\', $value);
        $escaped = str_replace('"', '\\"', $escaped);
        $escaped = str_replace('$', '\\$', $escaped);

        return '"' . $escaped . '"';
    }
}
