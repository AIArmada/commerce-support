<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @template TModel of Model
 */
final class OwnerBatchRunner
{
    private ?string $enabledConfigKey;

    private ?string $includeGlobalConfigKey;

    /**
     * @param  class-string<TModel>  $modelClass  Model to discover owner tuples from
     * @param  array{enabled?: string, include_global?: string}|null  $ownerConfig  Optional config keys;
     *                                                                              omitted keys fall back to
     *                                                                              the model's `ownerScopeConfig()`.
     */
    public function __construct(
        private readonly string $modelClass,
        ?array $ownerConfig = null,
    ) {
        $this->enabledConfigKey = $ownerConfig['enabled'] ?? null;
        $this->includeGlobalConfigKey = $ownerConfig['include_global'] ?? null;
    }

    public function run(callable $callback): mixed
    {
        if ($this->isOwnerDisabled()) {
            return $callback();
        }

        $owner = OwnerContext::resolve();
        if ($owner !== null) {
            return $callback();
        }

        $owners = $this->discoverOwners();

        if ($owners->isEmpty()) {
            return OwnerContext::withOwner(null, $callback);
        }

        return $this->runForOwners($owners, $callback);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function forEach(callable $callback): Collection
    {
        if ($this->isOwnerDisabled()) {
            return collect([$callback()]);
        }

        $owner = OwnerContext::resolve();
        if ($owner !== null) {
            return collect([$callback()]);
        }

        $owners = $this->discoverOwners();

        if ($owners->isEmpty()) {
            return collect([OwnerContext::withOwner(null, $callback)]);
        }

        return $this->collectForOwners($owners, $callback);
    }

    private function isOwnerDisabled(): bool
    {
        if ($this->enabledConfigKey !== null) {
            return ! (bool) config($this->enabledConfigKey, false);
        }

        $config = $this->modelOwnerScopeConfig();

        return $config !== null && ! $config->enabled;
    }

    /**
     * Discover, validate, and resolve every distinct owner tuple.
     *
     * The tuple scan streams via cursor; structural validation and owner
     * existence checks all run here so a malformed or orphaned tuple fails
     * fast before any callback executes.
     *
     * @return Collection<int, ?Model> Resolved owners (null = explicit global, deduplicated).
     */
    private function discoverOwners(): Collection
    {
        /** @var TModel $model */
        $model = new $this->modelClass;

        $columns = OwnerTupleColumns::forModelClass($this->modelClass);

        /** @var list<Model|null> $owners */
        $owners = [];
        $seenGlobal = false;

        $rows = $model->getConnection()->table($model->getTable())
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()
            ->orderBy($columns->ownerTypeColumn)
            ->orderBy($columns->ownerIdColumn)
            ->cursor();

        foreach ($rows as $row) {
            $parsed = OwnerTupleParser::fromRow($row, $columns);

            if ($parsed->isExplicitGlobal()) {
                if ($seenGlobal) {
                    continue;
                }

                $seenGlobal = true;
                $owners[] = null;

                continue;
            }

            $owners[] = $parsed->toOwnerModelOrFail();
        }

        return collect($owners);
    }

    /**
     * @param  Collection<int, ?Model>  $owners
     */
    private function runForOwners(Collection $owners, callable $callback): mixed
    {
        $results = $this->collectForOwners($owners, $callback);

        return $this->reduce($results);
    }

    /**
     * @param  Collection<int, ?Model>  $owners
     * @return Collection<int, mixed>
     */
    private function collectForOwners(Collection $owners, callable $callback): Collection
    {
        $execute = function () use ($owners, $callback): Collection {
            $results = [];

            foreach ($owners as $owner) {
                $results[] = OwnerContext::withOwner($owner, $callback);
            }

            return collect($results);
        };

        if ($this->resolveIncludeGlobal()) {
            return OwnerScopeOverride::withoutIncludeGlobal($execute);
        }

        return $execute();
    }

    private function resolveIncludeGlobal(): bool
    {
        if ($this->includeGlobalConfigKey !== null) {
            return (bool) config($this->includeGlobalConfigKey, false);
        }

        $config = $this->modelOwnerScopeConfig();

        return $config !== null && $config->includeGlobal;
    }

    private function modelOwnerScopeConfig(): ?OwnerScopeConfig
    {
        if (! method_exists($this->modelClass, 'ownerScopeConfig')) {
            return null;
        }

        /** @var OwnerScopeConfig $config */
        $config = $this->modelClass::ownerScopeConfig();

        return $config;
    }

    private function reduce(Collection $results): mixed
    {
        if ($results->isEmpty()) {
            return null;
        }

        if ($results->every(fn (mixed $r): bool => is_int($r))) {
            return $results->sum();
        }

        if ($results->every(fn (mixed $r): bool => is_array($r))) {
            return $results->reduce(function (?array $carry, array $result): array {
                if ($carry === null) {
                    return $result;
                }

                foreach ($result as $key => $value) {
                    if (! is_int($value) && ! is_float($value)) {
                        continue;
                    }

                    $current = $carry[$key] ?? 0;

                    $carry[$key] = is_int($current) || is_float($current) ? $current + $value : $value;
                }

                return $carry;
            });
        }

        return $results->first(fn (mixed $r): bool => $r !== null) ?? $results->last();
    }
}
