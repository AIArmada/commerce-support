<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\SignedDownloads;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Features\SignedDownloads\Actions\IssueSignedDownload;
use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use AIArmada\CommerceSupport\Support\OwnerContext;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

final readonly class SignedDownloadService implements SignedDownloadApi
{
    public function __construct(private IssueSignedDownload $issue) {}

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
    ): string {
        return $this->issue->execute($cachePrefix, $routeName, $routeParameters, $payload, $ttl, $owner, $userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payloadFromToken(mixed $token, string $cachePrefix): ?array
    {
        if ($cachePrefix === '' || preg_match('/[\s\x00-\x1F\x7F]/', $cachePrefix) === 1) {
            return null;
        }

        if (! is_string($token) || preg_match('/^[A-Za-z0-9]{64}$/D', $token) !== 1) {
            return null;
        }

        $payload = Cache::get($this->cacheKey($cachePrefix, $token));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isAuthorizedPayload(
        array $payload,
        string $resourceIdKey,
        string | int $expectedResourceId,
        ?RecordReference $owner,
        string | int | null $userId,
    ): bool {
        $cachedResourceId = $payload[$resourceIdKey] ?? null;
        $expectedId = (string) $expectedResourceId;

        if (! is_scalar($cachedResourceId) || (string) $cachedResourceId === '' || $expectedId === '') {
            return false;
        }

        if (! hash_equals((string) $cachedResourceId, $expectedId)) {
            return false;
        }

        $payloadUserId = $payload['user_id'] ?? null;

        if ($userId === null || ! is_scalar($payloadUserId) || (string) $payloadUserId === '') {
            return false;
        }

        if (! hash_equals((string) $payloadUserId, (string) $userId)) {
            return false;
        }

        $ownerType = $payload['owner_type'] ?? null;
        $ownerId = $payload['owner_id'] ?? null;

        if (($ownerType !== null && (! is_string($ownerType) || $ownerType === ''))
            || ($ownerId !== null && ((! is_string($ownerId) && ! is_int($ownerId)) || (string) $ownerId === ''))) {
            return false;
        }

        return $this->matchesOwner($owner, $ownerType, $ownerId);
    }

    private function cacheKey(string $cachePrefix, string $token): string
    {
        return "{$cachePrefix}:{$token}";
    }

    private function matchesOwner(?RecordReference $owner, ?string $expectedOwnerType, string | int | null $expectedOwnerId): bool
    {
        if ($expectedOwnerType === null && $expectedOwnerId === null) {
            if ($owner !== null) {
                return false;
            }

            return ! config('commerce-support.owner.enabled', false) || OwnerContext::isExplicitGlobal();
        }

        if ($owner === null || $expectedOwnerType === null || $expectedOwnerId === null) {
            return false;
        }

        return hash_equals($expectedOwnerType, $owner->type)
            && hash_equals((string) $expectedOwnerId, $owner->id);
    }
}
