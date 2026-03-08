<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class OwnerRouteBinding
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public static function resolve(string $modelClass, int | string $value, bool $includeGlobal = false): Model
    {
        return OwnerWriteGuard::findOrFailForOwner($modelClass, $value, OwnerContext::CURRENT, $includeGlobal);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     */
    public static function bind(string $parameter, string $modelClass, bool $includeGlobal = false): void
    {
        Route::bind($parameter, fn (string $value): Model => self::resolve($modelClass, $value, $includeGlobal));
    }
}
