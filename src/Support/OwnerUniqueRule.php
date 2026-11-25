<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;

/**
 * Scope validation uniqueness rules to the current owner.
 *
 * Filament `TextInput::make(...)->unique(...)` checks are global by default:
 * on owner-scoped models that both blocks owners from reusing each other's
 * slugs/codes and leaks cross-owner existence through validation errors.
 * This helper constrains the rule to the model's owner scope, honoring the
 * model's own scope config (enabled flag, include-global behavior, and any
 * customized owner column names), and fails closed when neither an owner nor
 * explicit global context is active.
 */
final class OwnerUniqueRule
{
    /**
     * @param  class-string<Model>  $modelClass  An owner-scoped model exposing ::ownerScopeConfig().
     */
    public static function scopeToOwner(Unique $rule, string $modelClass): Unique
    {
        if (! method_exists($modelClass, 'ownerScopeConfig')) {
            throw new InvalidArgumentException(sprintf('Model [%s] does not expose ::ownerScopeConfig().', $modelClass));
        }

        /** @var OwnerScopeConfig $config */
        $config = $modelClass::ownerScopeConfig();

        if (! $config->enabled) {
            return $rule;
        }

        $owner = OwnerContext::resolveConfigured($config);

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', $modelClass),
        );

        $includeGlobal = OwnerScopeOverride::suppressIncludeGlobal() ? false : $config->includeGlobal;
        $typeColumn = $config->ownerTypeColumn;
        $idColumn = $config->ownerIdColumn;

        if ($owner instanceof Model) {
            if ($includeGlobal) {
                return $rule->where(function (Builder $query) use ($owner, $typeColumn, $idColumn): void {
                    $query
                        ->where(function (Builder $ownerQuery) use ($owner, $typeColumn, $idColumn): void {
                            $ownerQuery
                                ->where($typeColumn, $owner->getMorphClass())
                                ->where($idColumn, (string) $owner->getKey());
                        })
                        ->orWhere(function (Builder $globalQuery) use ($typeColumn, $idColumn): void {
                            $globalQuery->whereNull($typeColumn)->whereNull($idColumn);
                        });
                });
            }

            return $rule
                ->where($typeColumn, $owner->getMorphClass())
                ->where($idColumn, (string) $owner->getKey());
        }

        return $rule
            ->whereNull($typeColumn)
            ->whereNull($idColumn);
    }
}
