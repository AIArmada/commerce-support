<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Persistence;

use AIArmada\CommerceSupport\Contracts\SlugRedirectRecorder;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Persist canonical slugs and record redirects on change.
 *
 * Complements `spatie/laravel-sluggable` self-healing URLs (which need
 * ID-bearing URLs): for pure-slug public URLs, old slugs must keep
 * resolving, so every canonical change records a redirect from the previous
 * slug through a host-provided {@see SlugRedirectRecorder}.
 */
final class CanonicalSlug
{
    /**
     * Set the model's canonical slug, recording a redirect when it changes.
     *
     * Any unsaved slug edit on the model is discarded in favor of the stored
     * value first; the write itself is quiet and timestamp-preserving.
     *
     * @return bool Whether a redirect was recorded. False when the slug was
     *              unchanged, and also when the slug changed but recording failed.
     */
    public static function persist(Model $model, string $slug, SlugRedirectRecorder $recorder): bool
    {
        return $model->getConnection()->transaction(function () use ($model, $slug, $recorder): bool {
            $current = $model->exists
                ? $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail()->getAttribute('slug')
                : $model->getAttribute('slug');

            $previous = self::normalize($current);
            $target = self::normalize($slug);

            if ($target === null) {
                throw new InvalidArgumentException('A canonical slug must not be blank.');
            }

            if ($model->exists) {
                $model->forceFill(['slug' => $current])->syncOriginalAttribute('slug');

                if ($previous === $target) {
                    return false;
                }

                $model->newQuery()->whereKey($model->getKey())->toBase()->update(['slug' => $target]);
                $model->forceFill(['slug' => $target])->syncOriginalAttribute('slug');
            } else {
                $model::withoutTimestamps(static fn () => $model->forceFill(['slug' => $target])->saveQuietly());
            }

            return self::syncChanged($model, $previous, $recorder);
        });
    }

    /**
     * Record a redirect for a slug the caller already changed.
     */
    public static function syncChanged(Model $model, mixed $previousSlug, SlugRedirectRecorder $recorder): bool
    {
        $reference = EloquentRecordReference::fromModel($model);
        $current = self::normalize($model->getAttribute('slug'));

        if ($reference === null || $current === null) {
            throw new InvalidArgumentException('A persisted record and canonical slug are required.');
        }

        return $recorder->record($reference, self::normalize($previousSlug), $current);
    }

    private static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
