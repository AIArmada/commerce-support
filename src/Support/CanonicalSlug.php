<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\SlugRedirectRecorder;
use Illuminate\Database\Eloquent\Model;

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
        $currentSlug = self::currentSlug($model);
        $normalizedCurrentSlug = self::normalize($currentSlug);
        $normalizedTargetSlug = self::normalize($slug);

        if (self::normalize($model->getAttribute('slug')) !== $normalizedCurrentSlug) {
            $model->forceFill(['slug' => $currentSlug]);
            $model->syncOriginal();
        }

        if ($normalizedCurrentSlug === $normalizedTargetSlug) {
            return false;
        }

        $previousSlug = $normalizedCurrentSlug;

        $model::withoutTimestamps(static function () use ($model, $slug): void {
            $model->forceFill(['slug' => $slug])->saveQuietly();
        });

        return self::syncChanged($model, $previousSlug, $recorder);
    }

    /**
     * Record a redirect for a slug the caller already changed.
     */
    public static function syncChanged(Model $model, mixed $previousSlug, SlugRedirectRecorder $recorder): bool
    {
        return $recorder->record($model, self::normalize($previousSlug));
    }

    private static function currentSlug(Model $model): mixed
    {
        if (! $model->exists || $model->getKey() === null) {
            return $model->getAttribute('slug');
        }

        return $model::query()->whereKey($model->getKey())->value('slug')
            ?? $model->getAttribute('slug');
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
