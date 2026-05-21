<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

final class ResolveProjectRootAction
{
    use AsAction;

    public function handle(): string
    {
        $cwd = getcwd();

        if (is_string($cwd) && file_exists($cwd . '/composer.json')) {
            return $cwd;
        }

        if (function_exists('\\Orchestra\\Testbench\\package_path')) {
            return \Orchestra\Testbench\package_path();
        }

        return base_path();
    }
}
