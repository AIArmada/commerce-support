<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Http;

use AIArmada\CommerceSupport\Support\ValidatedHttpTarget;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PinnedHttpClient
{
    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $headers
     */
    public function send(
        string $method,
        ValidatedHttpTarget $target,
        array $options = [],
        array $headers = [],
        int $connectTimeout = 3,
        int $timeout = 10,
        int $attempts = 1,
        int $retrySleepMilliseconds = 0,
    ): Response {
        $transportOptions = ['allow_redirects' => false];
        $resolveEntry = $target->curlResolveEntry();

        $pending = Http::withOptions($transportOptions)
            ->withHeaders($headers)
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->retry(max(1, $attempts), max(0, $retrySleepMilliseconds), throw: false);

        if ($resolveEntry !== null) {
            if (! extension_loaded('curl') || ! defined('CURLOPT_RESOLVE')) {
                throw new RuntimeException('The cURL extension with CURLOPT_RESOLVE is required for pinned HTTP transport.');
            }

            self::assertCurlTransport($pending);

            $pending->withOptions(['curl' => [constant('CURLOPT_RESOLVE') => [$resolveEntry]]]);
        }

        return $pending->send(mb_strtoupper($method), $target->url, $options);
    }

    /**
     * Refuse to send a pinned request when the effective transport cannot be
     * the default cURL-backed client (e.g. a custom Guzzle handler stack was
     * configured globally), because CURLOPT_RESOLVE would be silently ignored
     * and the DNS pin unenforced.
     */
    private static function assertCurlTransport(PendingRequest $pending): void
    {
        if (array_key_exists('handler', $pending->getOptions())) {
            throw new RuntimeException('Pinned HTTP transport requires the default cURL-backed client; a custom Guzzle handler is configured, so DNS-pin enforcement cannot be guaranteed. Refusing to send.');
        }
    }
}
