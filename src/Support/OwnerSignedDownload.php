<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class OwnerSignedDownload
{
    public const string TOKEN_QUERY_PARAM = 'token';

    /**
     * @param  array<string, mixed>  $routeParameters
     * @param  array<string, mixed>  $payload
     */
    public static function issueUrl(
        string $cachePrefix,
        string $routeName,
        array $routeParameters,
        array $payload,
        DateTimeInterface | DateInterval | int $ttl,
        ?Model $owner,
        string | int | null $userId,
    ): string {
        $token = (string) Str::ulid();

        Cache::put(
            self::cacheKey($cachePrefix, $token),
            array_merge($payload, [
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
                'user_id' => $userId,
            ]),
            $ttl,
        );

        return (string) URL::temporarySignedRoute(
            $routeName,
            self::expiresAt($ttl),
            array_merge($routeParameters, [
                self::TOKEN_QUERY_PARAM => $token,
            ]),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function payloadFromRequestToken(Request $request, string $cachePrefix): ?array
    {
        $token = $request->query(self::TOKEN_QUERY_PARAM);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $payload = Cache::get(self::cacheKey($cachePrefix, $token));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function isAuthorizedPayload(
        array $payload,
        string $resourceIdKey,
        string | int $expectedResourceId,
        ?Model $owner,
        string | int | null $userId,
    ): bool {
        $cachedResourceId = $payload[$resourceIdKey] ?? null;

        if ((string) $cachedResourceId !== (string) $expectedResourceId) {
            return false;
        }

        if ($userId === null || (string) ($payload['user_id'] ?? '') !== (string) $userId) {
            return false;
        }

        $ownerType = isset($payload['owner_type']) && is_string($payload['owner_type'])
            ? $payload['owner_type']
            : null;

        return self::matchesOwner($owner, $ownerType, $payload['owner_id'] ?? null);
    }

    private static function cacheKey(string $cachePrefix, string $token): string
    {
        return "{$cachePrefix}:{$token}";
    }

    private static function expiresAt(DateTimeInterface | DateInterval | int $ttl): DateTimeInterface
    {
        if ($ttl instanceof DateTimeInterface) {
            return $ttl;
        }

        if ($ttl instanceof DateInterval) {
            return CarbonImmutable::now()->add($ttl);
        }

        return CarbonImmutable::now()->addSeconds(max(1, $ttl));
    }

    private static function matchesOwner(?Model $owner, ?string $expectedOwnerType, string | int | null $expectedOwnerId): bool
    {
        if ($expectedOwnerType === null && $expectedOwnerId === null) {
            return $owner === null;
        }

        if ($owner === null || $expectedOwnerType === null || $expectedOwnerId === null) {
            return false;
        }

        return $owner->getMorphClass() === $expectedOwnerType
            && (string) $owner->getKey() === (string) $expectedOwnerId;
    }
}
