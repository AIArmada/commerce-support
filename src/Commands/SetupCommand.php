<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use AIArmada\CommerceSupport\Actions\UpsertEnvVariablesAction;
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
            if (confirm('Set CHIP environment?', default: false)) {
                $updates['CHIP_ENVIRONMENT'] = confirm('Use production mode?', default: false) ? 'production' : 'sandbox';
            }

            $collectApiKey = password(
                label: 'CHIP Collect API Key',
                required: false
            );
            if ($collectApiKey) {
                $updates['CHIP_COLLECT_API_KEY'] = $collectApiKey;
            }

            $collectBrandId = text(
                label: 'CHIP Collect Brand ID',
                placeholder: 'your-brand-id',
                required: false
            );
            if ($collectBrandId) {
                $updates['CHIP_COLLECT_BRAND_ID'] = $collectBrandId;
            }

            $collectPublicKey = text(
                label: 'CHIP Collect Public Key',
                required: false
            );
            if ($collectPublicKey) {
                $updates['CHIP_COLLECT_PUBLIC_KEY'] = $collectPublicKey;
            }

            $sendApiKey = password(
                label: 'CHIP Send API Key',
                required: false
            );
            if ($sendApiKey) {
                $updates['CHIP_SEND_API_KEY'] = $sendApiKey;
            }

            $sendApiSecret = password(
                label: 'CHIP Send API Secret',
                required: false
            );
            if ($sendApiSecret) {
                $updates['CHIP_SEND_API_SECRET'] = $sendApiSecret;
            }
        }

        // J&T Express
        if (confirm('Configure J&T Express shipping?', default: false)) {
            if (confirm('Set J&T environment?', default: false)) {
                $updates['JNT_ENVIRONMENT'] = confirm('Use production mode?', default: false) ? 'production' : 'testing';
            }

            $apiAccount = text(
                label: 'J&T Express API Account',
                required: false
            );
            if ($apiAccount) {
                $updates['JNT_API_ACCOUNT'] = $apiAccount;
            }

            $privateKey = password(
                label: 'J&T Express Private Key',
                required: false
            );
            if ($privateKey) {
                $updates['JNT_PRIVATE_KEY'] = $privateKey;
            }

            $customerCode = text(
                label: 'J&T Express Customer Code',
                required: false
            );
            if ($customerCode) {
                $updates['JNT_CUSTOMER_CODE'] = $customerCode;
            }

            $jntPassword = password(
                label: 'J&T Express Password',
                required: false
            );
            if ($jntPassword) {
                $updates['JNT_PASSWORD'] = $jntPassword;
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
        UpsertEnvVariablesAction::run(
            updates: $updates,
            force: (bool) $this->option('force'),
            warn: function (string $message): void {
                $this->components->warn($message);
            },
            info: function (string $message): void {
                $this->components->info($message);
            },
        );
    }
}
