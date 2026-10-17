<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RuntimeException;

/**
 * Explicit-ESCAPE LIKE predicates for user-supplied search input.
 *
 * SQLite has no default LIKE escape character, so a pattern escaped with
 * backslashes matches nothing unless the query declares an ESCAPE clause.
 * These helpers keep that declaration in one place; every caller gets the
 * same escaping and the same cross-driver behavior (ILIKE on pgsql).
 */
final class LikeSearch
{
    /**
     * The ESCAPE fragment for backslash-escaped patterns.
     *
     * SQLite has no default LIKE escape character, so these queries declare
     * `ESCAPE '\\'` explicitly. PostgreSQL uses ILIKE for case-insensitive
     * predicates; SQL Server-compatible paths keep the same escape fragment.
     *
     * @param  EloquentBuilder<Model>|QueryBuilder|ConnectionInterface  $source
     */
    public static function escapeClause(EloquentBuilder | QueryBuilder | ConnectionInterface $source): string
    {
        $connection = $source instanceof ConnectionInterface
            ? $source
            : $source->getConnection();
        $driver = ConnectionDriver::name($connection);

        return match ($driver) {
            'pgsql', 'sqlite', 'sqlsrv' => "ESCAPE '\\'",
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for escaped LIKE searches."),
        };
    }

    public static function escape(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    public static function contains(string $value): string
    {
        return '%' . self::escape($value) . '%';
    }

    public static function startsWith(string $value): string
    {
        return self::escape($value) . '%';
    }

    public static function endsWith(string $value): string
    {
        return '%' . self::escape($value);
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     * @return EloquentBuilder<Model>|QueryBuilder
     */
    public static function whereLike(EloquentBuilder | QueryBuilder $query, string $column, string $pattern, string $boolean = 'and'): EloquentBuilder | QueryBuilder
    {
        $operator = ConnectionDriver::name($query->getConnection()) === 'pgsql'
            ? 'ILIKE'
            : 'LIKE';

        $wrapped = $query->getGrammar()->wrap($column);
        $escape = self::escapeClause($query);

        return $query->whereRaw("{$wrapped} {$operator} ? {$escape}", [$pattern], $boolean);
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     * @return EloquentBuilder<Model>|QueryBuilder
     */
    public static function orWhereLike(EloquentBuilder | QueryBuilder $query, string $column, string $pattern): EloquentBuilder | QueryBuilder
    {
        return self::whereLike($query, $column, $pattern, 'or');
    }
}
