<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Data\PaymentActorData;
use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final class EloquentRecordReference
{
    public static function fromModel(?Model $model): ?RecordReference
    {
        if ($model === null) {
            return null;
        }

        $id = $model->getKey();

        if (! $model->exists || (! is_string($id) && ! is_int($id)) || (string) $id === '') {
            throw new InvalidArgumentException('A persisted record is required.');
        }

        return new RecordReference($model->getMorphClass(), (string) $id);
    }

    public static function actor(?Model $model): ?PaymentActorData
    {
        $reference = self::fromModel($model);

        if ($model === null || $reference === null) {
            return null;
        }

        return new PaymentActorData(
            reference: $reference,
            name: self::text($model->getAttribute('name')),
            email: self::text($model->getAttribute('email')),
            phone: self::text($model->getAttribute('phone')),
        );
    }

    public static function resolve(?RecordReference $reference): ?Model
    {
        if ($reference === null) {
            return null;
        }

        $class = Relation::getMorphedModel($reference->type) ?? $reference->type;

        if (! is_a($class, Model::class, true)) {
            throw new InvalidArgumentException('Record identity must resolve to a model.');
        }

        return (new $class)->newQuery()->whereKey($reference->id)->firstOrFail();
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && mb_trim((string) $value) !== '' ? mb_trim((string) $value) : null;
    }
}
