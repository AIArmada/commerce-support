<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Features\SignedDownloads\Actions;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use RuntimeException;

final class IssueSignedDownload
{
    /**
     * @param  array<string, mixed>  $routeParameters
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        string $cachePrefix,
        string $routeName,
        array $routeParameters,
        array $payload,
        DateTimeInterface | DateInterval | int $ttl,
        ?RecordReference $owner,
        string | int | null $userId,
    ): string {
        if ($cachePrefix === '' || preg_match('/[\s\x00-\x1F\x7F]/', $cachePrefix) === 1) {
            throw new InvalidArgumentException('Invalid signed download cache prefix.');
        }

        $now = CarbonImmutable::now();
        $expiresAt = match (true) {
            $ttl instanceof DateTimeInterface => CarbonImmutable::instance($ttl),
            $ttl instanceof DateInterval => $now->add($ttl),
            default => $now->addSeconds($ttl),
        };

        if ($expiresAt->lessThanOrEqualTo($now) || $userId === null || (string) $userId === '') {
            throw new InvalidArgumentException('A signed download requires a future expiry and an authenticated user.');
        }

        $token = bin2hex(random_bytes(32));
        $url = (string) URL::temporarySignedRoute(
            $routeName,
            $expiresAt,
            array_merge($routeParameters, [SignedDownloadApi::TOKEN_QUERY_PARAM => $token]),
        );

        if (! Cache::put(
            $cachePrefix . ':' . $token,
            array_merge($payload, [
                'owner_type' => $owner?->type,
                'owner_id' => $owner?->id,
                'user_id' => $userId,
            ]),
            $expiresAt,
        )) {
            throw new RuntimeException('Unable to persist signed download payload.');
        }

        return $url;
    }
}
