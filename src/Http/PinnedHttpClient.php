<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Http;

use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\CommerceSupport\Support\ValidatedHttpTarget;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class PinnedHttpClient
{
    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $headers
     * @param  (callable(PendingRequest, ValidatedHttpTarget): void)|null  $configure
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
        ?callable $configure = null,
    ): Response {
        $transportOptions = ['allow_redirects' => false];
        $resolveEntry = $target->curlResolveEntry();

        $pending = Http::withOptions($transportOptions)
            ->withHeaders($headers)
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->retry(max(1, $attempts), max(0, $retrySleepMilliseconds), throw: false);

        if ($configure !== null) {
            $configure($pending, $target);
        }

        if ($resolveEntry !== null) {
            if (! extension_loaded('curl') || ! defined('CURLOPT_RESOLVE')) {
                throw new RuntimeException('The cURL extension with CURLOPT_RESOLVE is required for pinned HTTP transport.');
            }

            // Guzzle request options that swap the handler (e.g. stream)
            // would silently drop CURLOPT_RESOLVE, so refuse them loudly.
            if (array_key_exists('stream', $options) || array_key_exists('handler', $options)) {
                throw new RuntimeException('Pinned HTTP transport cannot combine DNS pinning with custom handler options (stream/handler).');
            }

            self::assertCurlTransport($pending);

            $curlOptions = is_array($options['curl'] ?? null) ? $options['curl'] : [];
            $curlOptions[constant('CURLOPT_RESOLVE')] = [$resolveEntry];
            $options['curl'] = $curlOptions;
        }

        return $pending->send(mb_strtoupper($method), $target->url, $options);
    }

    /**
     * Follow up to $maxRedirects HTTP redirects safely, re-validating each hop through {@see PublicHttpUrlGuard}.
     *
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $headers
     * @param  (callable(PendingRequest, ValidatedHttpTarget, int): void)|null  $configure
     */
    public function sendFollowingRedirects(
        PublicHttpUrlGuard $guard,
        string $method,
        string $url,
        array $options = [],
        array $headers = [],
        int $maxRedirects = 3,
        int $connectTimeout = 3,
        int $timeout = 10,
        int $attempts = 1,
        int $retrySleepMilliseconds = 0,
        ?callable $configure = null,
    ): Response {
        $target = $guard->validate($url);

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $response = $this->send(
                method: $method,
                target: $target,
                options: $options,
                headers: $headers,
                connectTimeout: $connectTimeout,
                timeout: $timeout,
                attempts: $attempts,
                retrySleepMilliseconds: $retrySleepMilliseconds,
                configure: $configure === null
                    ? null
                    : static function (PendingRequest $pending, ValidatedHttpTarget $currentTarget) use ($configure, $hop): void {
                        $configure($pending, $currentTarget, $hop);
                    },
            );

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $location = mb_trim((string) $response->header('Location'));

            if ($location === '' || $hop === $maxRedirects) {
                throw new InvalidArgumentException('Too many or empty redirects from remote host.');
            }

            $nextUrl = self::resolveRelativeUrl($target->url, $location);
            $target = $guard->validate($nextUrl);
        }

        throw new InvalidArgumentException('Unable to resolve public redirect destination.');
    }

    /**
     * Download a URL safely up to $maxBytes across pinned redirect hops without unbounded memory buffering.
     *
     * @param  array<string, string>  $headers
     * @param  (callable(PendingRequest, ValidatedHttpTarget, int): void)|null  $configure
     */
    public function downloadToLimit(
        PublicHttpUrlGuard $guard,
        string $url,
        int $maxBytes,
        array $headers = [],
        int $maxRedirects = 3,
        int $connectTimeout = 2,
        int $timeout = 5,
        ?callable $configure = null,
    ): ?Response {
        $maxBytes = max(1, $maxBytes);
        $curlOptions = [];

        if (defined('CURLOPT_MAXFILESIZE')) {
            $curlOptions[constant('CURLOPT_MAXFILESIZE')] = $maxBytes + 1;
        }

        if (defined('CURLOPT_LOW_SPEED_LIMIT') && defined('CURLOPT_LOW_SPEED_TIME')) {
            $curlOptions[constant('CURLOPT_LOW_SPEED_LIMIT')] = 1024;
            $curlOptions[constant('CURLOPT_LOW_SPEED_TIME')] = max(1, $timeout);
        }

        return $this->sendFollowingRedirects(
            guard: $guard,
            method: 'GET',
            url: $url,
            options: $curlOptions !== [] ? ['curl' => $curlOptions] : [],
            headers: $headers,
            maxRedirects: $maxRedirects,
            connectTimeout: $connectTimeout,
            timeout: $timeout,
            attempts: 1,
            retrySleepMilliseconds: 0,
            configure: $configure,
        );
    }

    public function readBoundedBody(Response $response, int $maxBytes): ?string
    {
        $maxBytes = max(1, $maxBytes);
        $contentLength = $response->header('Content-Length');

        if (is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
            return null;
        }

        $stream = $response->toPsrResponse()->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';

        while (! $stream->eof()) {
            $remaining = $maxBytes - mb_strlen($body, '8bit');
            $chunk = $stream->read(min(8192, $remaining + 1));

            if ($chunk === '') {
                break;
            }

            $body .= $chunk;

            if (mb_strlen($body, '8bit') > $maxBytes) {
                return null;
            }
        }

        return $body;
    }

    public static function resolveRelativeUrl(string $baseUrl, string $location): string
    {
        $location = mb_trim($location);

        if ($location === '' || preg_match('/[\x00-\x20]/', $location) === 1) {
            throw new InvalidArgumentException('Invalid redirect location.');
        }

        $parsedLocation = parse_url($location);

        if (is_array($parsedLocation) && isset($parsedLocation['scheme'])) {
            return $location;
        }

        $base = parse_url($baseUrl);

        if (! is_array($base) || ! isset($base['scheme'], $base['host'])) {
            throw new InvalidArgumentException('Invalid base URL for redirect.');
        }

        $port = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($location, '//')) {
            return $base['scheme'] . ':' . $location;
        }

        $origin = $base['scheme'] . '://' . $base['host'] . $port;

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $basePath = (string) ($base['path'] ?? '/');
        $dir = mb_rtrim(dirname($basePath), '/');

        return $origin . $dir . '/' . $location;
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
