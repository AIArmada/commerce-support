<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Owner-scoped filesystem path builder and resolver.
 *
 * Enforces `owners/{ownerScopeKey}/...` structure to prevent cross-tenant
 * file access in a shared-storage, single-database multitenancy model.
 *
 * @example
 * ```php
 * // Build an owner-scoped storage path
 * $path = OwnerFilesystem::path($owner, 'invoices/2025-01.pdf');
 * // Result: "owners/sha256hash/invoices/2025-01.pdf"
 *
 * // Store a file for an owner
 * OwnerFilesystem::put($owner, 'invoices/2025-01.pdf', $content);
 *
 * // Retrieve a file (with access check)
 * $content = OwnerFilesystem::get($owner, 'invoices/2025-01.pdf');
 *
 * // Delete an owner's file
 * OwnerFilesystem::delete($owner, 'invoices/2025-01.pdf');
 *
 * // Check if file exists for owner
 * if (OwnerFilesystem::exists($owner, 'invoices/2025-01.pdf')) {
 *     // ...
 * }
 * ```
 */
final class OwnerFilesystem
{
    /**
     * Build an owner-scoped filesystem path.
     *
     * @param  Model|OwnerScopeIdentifiable|null  $owner  The owner model or scope-identifiable object (null = global context)
     * @param  string  $relativePath  The relative path within owner's scope (e.g., 'invoices/2025-01.pdf')
     * @return string The full scoped path (e.g., 'owners/sha256hash/invoices/2025-01.pdf')
     *
     * @throws InvalidArgumentException if relativePath contains invalid traversal attempts
     */
    public static function path(Model | OwnerScopeIdentifiable | null $owner, string $relativePath): string
    {
        if ($relativePath === '') {
            throw new InvalidArgumentException('Relative path cannot be empty.');
        }

        // Prevent directory traversal attacks
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new InvalidArgumentException('Relative path contains invalid traversal characters.');
        }

        $ownerKey = OwnerScopeKey::forOwner($owner);

        return "owners/{$ownerKey}/{$relativePath}";
    }

    /**
     * Store a file for an owner.
     *
     * @param  string  $relativePath  Path relative to owner scope
     * @param  string|resource  $contents
     * @param  array<string, mixed>  $options  Storage options (visibility, etc.)
     */
    public static function put(Model | OwnerScopeIdentifiable | null $owner, string $relativePath, $contents, array $options = []): bool
    {
        return Storage::disk()->put(
            self::path($owner, $relativePath),
            $contents,
            $options
        );
    }

    /**
     * Retrieve a file for an owner (with scope verification).
     *
     * @param  string|null  $default  Default content if file not found
     */
    public static function get(Model | OwnerScopeIdentifiable | null $owner, string $relativePath, ?string $default = null): ?string
    {
        $fullPath = self::path($owner, $relativePath);

        if (! Storage::disk()->exists($fullPath)) {
            return $default;
        }

        return Storage::disk()->get($fullPath);
    }

    /**
     * Check if a file exists for an owner.
     */
    public static function exists(Model | OwnerScopeIdentifiable | null $owner, string $relativePath): bool
    {
        return Storage::disk()->exists(self::path($owner, $relativePath));
    }

    /**
     * Delete a file for an owner.
     */
    public static function delete(Model | OwnerScopeIdentifiable | null $owner, string $relativePath): bool
    {
        return Storage::disk()->delete(self::path($owner, $relativePath));
    }

    /**
     * Get the URL for an owner-scoped file (if driver supports public URLs).
     *
     * Use this only for files that should be publicly accessible, or guard with route auth.
     */
    public static function url(Model | OwnerScopeIdentifiable | null $owner, string $relativePath): ?string
    {
        $fullPath = self::path($owner, $relativePath);

        if (! Storage::disk()->exists($fullPath)) {
            return null;
        }

        return Storage::disk()->url($fullPath);
    }

    /**
     * Get the temporary URL for an owner-scoped file (if driver supports it).
     *
     * @param  DateTimeInterface|DateInterval|int  $expiration
     */
    public static function temporaryUrl(Model | OwnerScopeIdentifiable | null $owner, string $relativePath, $expiration): ?string
    {
        $fullPath = self::path($owner, $relativePath);

        if (! Storage::disk()->exists($fullPath)) {
            return null;
        }

        try {
            return Storage::disk()->temporaryUrl($fullPath, $expiration);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Copy a file for an owner to a new path within the same owner scope.
     *
     * @param  string  $from  Source path (relative to owner scope)
     * @param  string  $to  Destination path (relative to owner scope)
     */
    public static function copy(Model | OwnerScopeIdentifiable | null $owner, string $from, string $to): bool
    {
        return Storage::disk()->copy(
            self::path($owner, $from),
            self::path($owner, $to)
        );
    }

    /**
     * Move a file for an owner within the same owner scope.
     */
    public static function move(Model | OwnerScopeIdentifiable | null $owner, string $from, string $to): bool
    {
        return Storage::disk()->move(
            self::path($owner, $from),
            self::path($owner, $to)
        );
    }
}
