<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @template TModel of Model
 */
final class OwnerBatchRunner
{
    private ?string $enabledConfigKey;

    private ?string $includeGlobalConfigKey;

    /**
     * @param  class-string<TModel>  $modelClass  Model to discover owner tuples from
     * @param  array{enabled?: string, include_global?: string}|null  $ownerConfig
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
        return $this->enabledConfigKey !== null
            && ! (bool) config($this->enabledConfigKey, false);
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

        /** @var Collection<int, ?Model> $owners */
        $owners = collect();
        $seenGlobal = false;

        $rows = DB::table($model->getTable())
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
                $owners->push(null);

                continue;
            }

            $owners->push($parsed->toOwnerModelOrFail());
        }

        // @phpstan-ignore-next-line Collection covariance false positive with exact array shape.
        return $owners;
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
        return $this->includeGlobalConfigKey !== null
            && (bool) config($this->includeGlobalConfigKey, false);
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
                    $carry[$key] = ($carry[$key] ?? 0) + $value;
                }

                return $carry;
            });
        }

        return $results->first(fn (mixed $r): bool => $r !== null) ?? $results->last();
    }
}
