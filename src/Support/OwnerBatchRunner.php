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

        $columns = OwnerTupleColumns::forModelClass($this->modelClass);

        $owners = $this->discoverOwners($columns);

        if ($owners->isEmpty()) {
            return OwnerContext::withOwner(null, $callback);
        }

        return $this->runForOwners($owners, $columns, $callback);
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

        $columns = OwnerTupleColumns::forModelClass($this->modelClass);

        $owners = $this->discoverOwners($columns);

        if ($owners->isEmpty()) {
            return collect([OwnerContext::withOwner(null, $callback)]);
        }

        return $this->collectForOwners($owners, $columns, $callback);
    }

    private function isOwnerDisabled(): bool
    {
        return $this->enabledConfigKey !== null
            && ! (bool) config($this->enabledConfigKey, false);
    }

    private function discoverOwners(OwnerTupleColumns $columns): Collection
    {
        /** @var TModel $model */
        $model = new $this->modelClass;

        return DB::table($model->getTable())
            ->select([$columns->ownerTypeColumn, $columns->ownerIdColumn])
            ->distinct()
            ->get();
    }

    private function runForOwners(Collection $owners, OwnerTupleColumns $columns, callable $callback): mixed
    {
        $results = $this->collectForOwners($owners, $columns, $callback);

        return $this->reduce($results);
    }

    /**
     * @return Collection<int, mixed>
     */
    private function collectForOwners(Collection $owners, OwnerTupleColumns $columns, callable $callback): Collection
    {
        $includeGlobal = $this->resolveIncludeGlobal();

        if ($includeGlobal) {
            config()->set($this->includeGlobalConfigKey, false);
        }

        try {
            $processedGlobal = false;
            $results = [];

            foreach ($owners as $row) {
                $parsed = OwnerTupleParser::fromRow($row, $columns);

                if ($parsed->isExplicitGlobal()) {
                    if ($processedGlobal) {
                        continue;
                    }

                    $processedGlobal = true;
                }

                $results[] = OwnerContext::withOwner(
                    $parsed->toOwnerModel(),
                    $callback,
                );
            }
        } finally {
            if ($includeGlobal) {
                config()->set($this->includeGlobalConfigKey, true);
            }
        }

        return collect($results);
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
