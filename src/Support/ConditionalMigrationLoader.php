<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Closure;
use Illuminate\Support\ServiceProvider;

final class ConditionalMigrationLoader
{
    /**
     * @var array<string, string>
     */
    private static array $syntheticTimestamps = [];

    /**
     * Vendor stubs that must be safe to re-run, keyed by migration suffix.
     *
     * Materialized copies are renamed per boot state, so a re-run against an
     * existing table must no-op instead of crashing.
     *
     * @var array<string, string>
     */
    private const IDEMPOTENT_VENDOR_TABLES = [
        'create_activity_log_table' => 'activity_log',
        'create_media_table' => 'media',
    ];

    public static function loadDirectoryIfMissing(ServiceProvider $provider, string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob(mb_rtrim($directory, '/') . '/*');

        if (! is_array($files)) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            if (! self::isMigrationFile($file)) {
                continue;
            }

            self::loadFileIfMissing($provider, $file);
        }
    }

    public static function loadFileIfMissing(ServiceProvider $provider, string $migrationPath, ?string $publishedSuffix = null): void
    {
        if (! is_file($migrationPath)) {
            return;
        }

        $publishedSuffix ??= self::normalizeMigrationSuffix($migrationPath);

        if ($publishedSuffix === null || self::publishedMigrationExists($publishedSuffix)) {
            return;
        }

        $runtimeMigrationPath = self::materializeRuntimeMigration($migrationPath, $publishedSuffix);

        Closure::bind(
            function (string $path): void {
                $this->loadMigrationsFrom($path);
            },
            $provider,
            $provider::class,
        )($runtimeMigrationPath);
    }

    private static function isMigrationFile(string $path): bool
    {
        return str_ends_with($path, '.php')
            || str_ends_with($path, '.stub')
            || str_ends_with($path, '.php.stub');
    }

    private static function normalizeMigrationSuffix(string $path): ?string
    {
        $basename = basename($path);
        $normalized = preg_replace('/(\.php\.stub|\.php|\.stub)$/', '', $basename);

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        $withoutTimestamp = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $normalized);

        if (! is_string($withoutTimestamp) || $withoutTimestamp === '') {
            return null;
        }

        return $withoutTimestamp;
    }

    private static function publishedMigrationExists(string $suffix): bool
    {
        $matches = glob(database_path("migrations/*_{$suffix}.php"));

        return is_array($matches) && $matches !== [];
    }

    private static function materializeRuntimeMigration(string $migrationPath, string $publishedSuffix): string
    {
        if (! str_ends_with($migrationPath, '.stub')) {
            return $migrationPath;
        }

        $runtimeDirectory = storage_path('framework/cache/aiarmada-commerce-migrations');

        if (! is_dir($runtimeDirectory)) {
            mkdir($runtimeDirectory, 0755, true);
        }

        $filename = basename($migrationPath);

        if (str_ends_with($filename, '.php.stub')) {
            $filename = mb_substr($filename, 0, -5);
        } elseif (str_ends_with($filename, '.stub')) {
            $filename = $publishedSuffix . '.php';
        }

        if (! preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $filename)) {
            $basename = preg_replace('/\.php$/', '', $filename);

            if (is_string($basename) && $basename !== '') {
                self::$syntheticTimestamps[$basename] ??= sprintf(
                    '1970_01_01_%06d',
                    count(self::$syntheticTimestamps) + 1,
                );

                $filename = self::$syntheticTimestamps[$basename] . '_' . $basename . '.php';
            }
        }

        $runtimePath = mb_rtrim($runtimeDirectory, '/') . '/' . $filename;
        $contents = file_get_contents($migrationPath);

        if (is_string($contents) && isset(self::IDEMPOTENT_VENDOR_TABLES[$publishedSuffix])) {
            $contents = self::wrapWithTableGuard($migrationPath, self::IDEMPOTENT_VENDOR_TABLES[$publishedSuffix]);
        }

        if (is_string($contents) && (! is_file($runtimePath) || file_get_contents($runtimePath) !== $contents)) {
            file_put_contents($runtimePath, $contents);
        }

        return $runtimePath;
    }

    /**
     * Wrap a vendor stub so re-running it against an existing table is a no-op.
     *
     * The installed vendor file stays the source of truth for the schema; the
     * wrapper only guards and delegates.
     */
    private static function wrapWithTableGuard(string $migrationPath, string $table): string
    {
        $stub = var_export($migrationPath, true);
        $tableName = var_export($table, true);

        return <<<PHP
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    if (Schema::hasTable({$tableName})) {
                        return;
                    }

                    \$this->inner()->up();
                }

                private function inner(): Migration
                {
                    \$migration = require {$stub};

                    if (! \$migration instanceof Migration) {
                        throw new RuntimeException('Unsupported migration stub shape, expected an anonymous migration class.');
                    }

                    return \$migration;
                }
            };

            PHP;
    }
}
