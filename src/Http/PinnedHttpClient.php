<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Http;

use AIArmada\CommerceSupport\Support\ValidatedHttpTarget;
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

        if ($resolveEntry !== null) {
            if (! defined('CURLOPT_RESOLVE')) {
                throw new RuntimeException('The cURL extension with CURLOPT_RESOLVE is required for pinned HTTP transport.');
            }

            $transportOptions['curl'] = [constant('CURLOPT_RESOLVE') => [$resolveEntry]];
        }

        return Http::withOptions($transportOptions)
            ->withHeaders($headers)
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->retry(max(1, $attempts), max(0, $retrySleepMilliseconds), throw: false)
            ->send(mb_strtoupper($method), $target->url, $options);
    }
}
