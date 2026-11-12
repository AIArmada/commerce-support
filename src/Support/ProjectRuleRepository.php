<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Support\Str;
use Laravel\Boost\Rules\RuleRepository;

/**
 * Rules repository that resolves relative paths against the real project root.
 *
 * Boost binds its repository with `base_path('.ai/rules')`, which points at the
 * Testbench skeleton in this monorepo, so index rows would fall back to
 * absolute paths.
 */
final class ProjectRuleRepository extends RuleRepository
{
    public function __construct(string $directory, private readonly string $projectRoot)
    {
        parent::__construct($directory);
    }

    public function relativePath(string $path): string
    {
        $path = Str::replace(DIRECTORY_SEPARATOR, '/', $path);
        $base = Str::finish(Str::replace(DIRECTORY_SEPARATOR, '/', $this->projectRoot), '/');

        return Str::startsWith($path, $base)
            ? Str::after($path, $base)
            : mb_ltrim($path, '/');
    }
}
