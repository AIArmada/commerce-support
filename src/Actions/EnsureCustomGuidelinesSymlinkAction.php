<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class EnsureCustomGuidelinesSymlinkAction
{
    use AsAction;

    /**
     * @param  callable(string): void|null  $warn
     */
    public function handle(string $projectRoot, ?callable $warn = null): void
    {
        $sourceDir = $projectRoot . '/.ai/guidelines';
        $targetDir = base_path('.ai');
        $targetLink = $targetDir . '/guidelines';

        if (! is_dir($sourceDir)) {
            return;
        }

        if (is_link($targetLink) && readlink($targetLink) === $sourceDir) {
            return;
        }

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true)) {
            throw new RuntimeException("Failed to create directory: {$targetDir}");
        }

        if (is_link($targetLink)) {
            if (! unlink($targetLink)) {
                throw new RuntimeException("Failed to remove existing symlink: {$targetLink}");
            }
        } elseif (file_exists($targetLink)) {
            if ($warn !== null) {
                $warn("Skipping symlink creation; a real path already exists at: {$targetLink}");
            }

            return;
        }

        if (! symlink($sourceDir, $targetLink)) {
            throw new RuntimeException("Failed to create symlink: {$targetLink}");
        }
    }
}
