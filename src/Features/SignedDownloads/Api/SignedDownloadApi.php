<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\SignedDownloads\Api;

use AIArmada\CommerceSupport\Data\RecordReference;
use DateInterval;
use DateTimeInterface;

interface SignedDownloadApi
{
    public const string TOKEN_QUERY_PARAM = 'token';

    /**
     * @param  array<string, mixed>  $routeParameters
     * @param  array<string, mixed>  $payload
     */
    public function issueUrl(
        string $cachePrefix,
        string $routeName,
        array $routeParameters,
        array $payload,
        DateTimeInterface | DateInterval | int $ttl,
        ?RecordReference $owner,
        string | int | null $userId,
    ): string;

    /** @return array<string, mixed>|null */
    public function payloadFromToken(mixed $token, string $cachePrefix): ?array;

    /** @param array<string, mixed> $payload */
    public function isAuthorizedPayload(
        array $payload,
        string $resourceIdKey,
        string | int $expectedResourceId,
        ?RecordReference $owner,
        string | int | null $userId,
    ): bool;
}
