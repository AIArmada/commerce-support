<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generic unique-slug generator for any Eloquent model with a `slug` column.
 *
 * Usage from a package model:
 *   $slug = SlugGenerator::generate($reference, 'title');
 *
 * Configurable via model key:
 *   $slug = SlugGenerator::generate($event, 'title', maxLength: 100);
 */
final class SlugGenerator
{
    private const int DEFAULT_MAX_LENGTH = 200;

    private const string DEFAULT_FALLBACK = 'untitled';

    /**
     * Generate a unique slug for the given model.
     *
     * @param  Model  $model  A model instance (may be new or existing).
     * @param  string  $source  Attribute name to derive the slug from.
     * @param  int  $maxLength  Maximum slug length (default 200).
     */
    public static function generate(Model $model, string $source, int $maxLength = self::DEFAULT_MAX_LENGTH): string
    {
        $maxLength = max(1, $maxLength);

        $baseSlug = Str::limit(
            Str::slug((string) $model->getAttribute($source)),
            $maxLength,
            '',
        );

        if ($baseSlug === '') {
            $baseSlug = Str::limit(self::DEFAULT_FALLBACK, $maxLength, '');
        }

        $slug = $baseSlug;
        $suffix = 1;

        while (self::slugExists($model::class, $slug, $model->exists ? (string) $model->getKey() : null)) {
            $suffixValue = '-' . $suffix;
            $slug = Str::limit(
                $baseSlug,
                max(1, $maxLength - mb_strlen($suffixValue)),
                '',
            ) . $suffixValue;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Check whether a slug already exists for a given model class.
     *
     * @param  class-string<Model>  $class
     */
    public static function exists(string $class, string $slug, ?string $ignoreId = null): bool
    {
        $query = $class::query()->where('slug', $slug);

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * @param  class-string<Model>  $class
     */
    private static function slugExists(string $class, string $slug, ?string $ignoreId): bool
    {
        return self::exists($class, $slug, $ignoreId);
    }
}
