<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Http\Request;
use Stringable;

/**
 * Stable submitter identity for authenticated users and guests alike.
 *
 * Authenticated requests resolve to `user:{id}`; guests resolve to
 * `guest:{sha256}` over IP + user agent, so guest-capable submissions
 * (reports, reviews, applications) and rate-limit keys share one format.
 * The guest half is a one-way hash: it identifies repeat submitters without
 * storing their IP or user agent.
 */
final class RequestFingerprint
{
    public static function resolve(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ((is_string($userId) || is_int($userId) || $userId instanceof Stringable) && (string) $userId !== '') {
            return 'user:' . $userId;
        }

        $ipAddress = (string) ($request->ip() ?? 'unknown-ip');
        $userAgent = mb_trim((string) ($request->userAgent() ?? 'unknown-agent'));

        return 'guest:' . hash('sha256', "{$ipAddress}|{$userAgent}");
    }
}
