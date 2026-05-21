<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Actions\ResolveOwnedModelOrFailAction;
use Illuminate\Database\Eloquent\Model;

final class OwnerWriteGuard
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public static function findOrFailForOwner(
        string $modelClass,
        int | string $id,
        Model | string | null $owner = OwnerContext::CURRENT,
        bool $includeGlobal = false,
        ?string $message = null,
    ): Model {
        /** @var TModel $model */
        $model = ResolveOwnedModelOrFailAction::run(
            modelClass: $modelClass,
            id: $id,
            owner: $owner,
            includeGlobal: $includeGlobal,
            message: $message,
        );

        return $model;
    }
}
