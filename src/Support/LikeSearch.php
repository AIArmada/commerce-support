<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Explicit-ESCAPE LIKE predicates for user-supplied search input.
 *
 * SQLite has no default LIKE escape character, so a pattern escaped with
 * backslashes matches nothing unless the query declares `ESCAPE '\'`.
 * These helpers keep that declaration in one place; every caller gets the
 * same escaping and the same cross-driver behavior (ILIKE on pgsql).
 */
final class LikeSearch
{
    /**
     * The driver-aware ESCAPE fragment for backslash-escaped patterns.
     *
     * MySQL string literals treat backslash as an escape character, so
     * `ESCAPE '\'` is an unterminated literal there and must be written
     * `ESCAPE '\\'` instead. SQLite, Postgres, and SQL Server string
     * literals have no backslash escapes, so `ESCAPE '\'` is correct.
     *
     * @param  EloquentBuilder<Model>|QueryBuilder|ConnectionInterface  $source
     */
    public static function escapeClause(EloquentBuilder | QueryBuilder | ConnectionInterface $source): string
    {
        $connection = $source instanceof ConnectionInterface
            ? $source
            : $source->getConnection();

        return ConnectionDriver::name($connection) === 'mysql'
            ? "ESCAPE '\\\\'"
            : "ESCAPE '\\'";
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
