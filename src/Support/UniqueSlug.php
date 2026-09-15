<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Batch-friendly programmatic unique-slug builder for any model with a `slug` column.
 *
 * This complements `spatie/laravel-sluggable` (model-event generation) rather than
 * replacing it: use it for explicit programmatic flows such as bulk imports,
 * console backfills, and save actions that assemble slugs from several parts.
 * Existing slugs are preloaded in one query and resolved in memory, and the
 * LIKE lookup uses {@see LikeSearch} so drivers share the same escaping
 * (ILIKE on pgsql, which errs toward uniqueness on case variants).
 */
final class UniqueSlug
{
    private const int MAX_SLUG_LENGTH = 200;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $middleSegments  Extra segments inserted between the base and the numeric suffix.
     * @param  int|string|null  $ignoreKey  Primary key excluded from the collision check (record updates). Empty strings are treated as null.
     * @param  bool  $withoutGlobalScopes  Check collisions across all rows, ignoring global scopes such as owner scoping. Needed when the slug feeds a global namespace (pure-slug public URLs) rather than an owner-scoped one.
     */
    public static function build(
        string $modelClass,
        string $baseSlug,
        array $middleSegments = [],
        string $trailingSuffix = '',
        int | string | null $ignoreKey = null,
        bool $withoutGlobalScopes = false,
    ): string {
        if ($baseSlug === '') {
            throw new InvalidArgumentException('The base slug must not be empty.');
        }

        $ignoreKey = $ignoreKey === '' ? null : $ignoreKey;

        $query = $modelClass::query();

        if ($withoutGlobalScopes) {
            $query->withoutGlobalScopes();
        }

        $query->where(function (EloquentBuilder | QueryBuilder $query) use ($baseSlug): void {
            $query->where('slug', $baseSlug);
            LikeSearch::orWhereLike($query, 'slug', LikeSearch::escape($baseSlug) . '-%');

            if (mb_strlen($baseSlug) > self::MAX_SLUG_LENGTH) {
                LikeSearch::orWhereLike($query, 'slug', LikeSearch::escape(mb_substr($baseSlug, 0, self::MAX_SLUG_LENGTH)) . '%');
            }
        });

        if ($ignoreKey !== null) {
            $query->whereKeyNot($ignoreKey);
        }

        $slugSet = array_flip($query
            ->pluck('slug')
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->all());

        $sequence = 1;
        $seenCandidates = [];

        do {
            $candidateParts = [$baseSlug];

            foreach ($middleSegments as $segment) {
                if ($segment !== '') {
                    $candidateParts[] = $segment;
                }
            }

            if ($sequence > 1) {
                $candidateParts[] = (string) $sequence;
            }

            if ($trailingSuffix !== '') {
                $candidateParts[] = $trailingSuffix;
            }

            $candidate = mb_substr(implode('-', $candidateParts), 0, self::MAX_SLUG_LENGTH);

            if (isset($seenCandidates[$candidate])) {
                // Truncation collapsed distinct candidates onto one string:
                // escape with a deterministic hash, verifying each escape
                // against the database since escapes fall outside the
                // preloaded set.
                do {
                    $sequence++;
                    $candidate = mb_substr($candidate, 0, self::MAX_SLUG_LENGTH - 7) . '-' . mb_substr(md5((string) $sequence), 0, 6);
                } while (isset($slugSet[$candidate]) || self::slugExists($modelClass, $candidate, $ignoreKey, $withoutGlobalScopes));
            }

            $seenCandidates[$candidate] = true;
            $sequence++;
        } while (isset($slugSet[$candidate]));

        return $candidate;
    }

    private static function slugExists(string $modelClass, string $slug, int | string | null $ignoreKey, bool $withoutGlobalScopes): bool
    {
        $query = $modelClass::query();

        if ($withoutGlobalScopes) {
            $query->withoutGlobalScopes();
        }

        if ($ignoreKey !== null) {
            $query->whereKeyNot($ignoreKey);
        }

        return $query->where('slug', $slug)->exists();
    }
}
