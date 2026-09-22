<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\PublicDnsResolver;
use Closure;
use InvalidArgumentException;
use Throwable;

final class PublicHttpUrlGuard
{
    /**
     * Hosts exempted from the public-IP precondition via allowHostsForTesting().
     *
     * @var list<string>|null
     */
    private static ?array $testAllowedHosts = null;

    private readonly PublicDnsResolver $dnsResolver;

    /**
     * Exempt hosts from the public-IP precondition for local/test use.
     *
     * Entries may be exact hostnames ('merchant.test') or suffixes
     * ('.test'). Matching hosts resolve via local DNS and skip the
     * public-IP check; every other validation (scheme, port, credentials,
     * fragments) still applies. Call only from local/test bootstrap —
     * never in production code paths.
     *
     * @param  list<string>  $hosts
     */
    public static function allowHostsForTesting(array $hosts): void
    {
        self::$testAllowedHosts = array_values(array_unique(array_map(
            static fn (string $host): string => mb_strtolower(mb_trim($host)),
            $hosts
        )));
    }

    public static function clearTestAllowedHosts(): void
    {
        self::$testAllowedHosts = null;
    }

    /**
     * @param  PublicDnsResolver|callable(string): list<string>|null  $dnsResolver
     */
    public function __construct(PublicDnsResolver | callable | null $dnsResolver = null)
    {
        if ($dnsResolver instanceof PublicDnsResolver) {
            $this->dnsResolver = $dnsResolver;

            return;
        }

        if (is_callable($dnsResolver)) {
            $callback = Closure::fromCallable($dnsResolver);
            $this->dnsResolver = new class($callback) implements PublicDnsResolver
            {
                /** @param Closure(string): list<string> $callback */
                public function __construct(private readonly Closure $callback) {}

                public function resolve(string $hostname): array
                {
                    return ($this->callback)($hostname);
                }
            };

            return;
        }

        $this->dnsResolver = new SystemPublicDnsResolver;
    }

    public function isAllowed(string $url): bool
    {
        try {
            $this->validate($url);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function assertAllowed(string $url): void
    {
        $this->validate($url);
    }

    public function validate(string $url): ValidatedHttpTarget
    {
        $url = mb_trim($url);
        $parts = parse_url($url);

        if ($url === '' || ! is_array($parts)) {
            throw new InvalidArgumentException('Outbound URL must be a valid absolute HTTP URL.');
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Outbound URL scheme must be HTTP or HTTPS.');
        }

        if (isset($parts['fragment'])) {
            throw new InvalidArgumentException('Outbound URLs must not contain fragments.');
        }

        if (array_key_exists('user', $parts) || array_key_exists('pass', $parts)) {
            throw new InvalidArgumentException('Outbound URLs must not contain embedded credentials.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $expectedPort = $scheme === 'https' ? 443 : 80;

        if ($port !== $expectedPort) {
            throw new InvalidArgumentException('Outbound URL port must match the standard port for its scheme.');
        }

        $host = mb_strtolower(mb_trim((string) ($parts['host'] ?? ''), "[] \t\n\r\0\x0B."));

        if ($host === '' || $this->isLocalHostname($host)) {
            throw new InvalidArgumentException('Outbound URL host must be a public fully-qualified host.');
        }

        if (self::isTestAllowedHost($host)) {
            return $this->validateTestHost($scheme, $host, $port, (string) ($parts['path'] ?? ''), isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        $isIpLiteral = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if ($isIpLiteral) {
            $addresses = [$host];
        } else {
            if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false || ! str_contains($host, '.')) {
                throw new InvalidArgumentException('Outbound URL host must be a valid fully-qualified domain name.');
            }

            try {
                $addresses = $this->dnsResolver->resolve($host);
            } catch (Throwable $exception) {
                throw new InvalidArgumentException('Outbound URL host could not be resolved safely.', previous: $exception);
            }
        }

        if ($addresses === []) {
            throw new InvalidArgumentException('Outbound URL host did not resolve to an IP address.');
        }

        $validated = [];

        foreach ($addresses as $address) {
            if (! is_string($address) || ! $this->isPublicIp($address)) {
                throw new InvalidArgumentException('Outbound URL host must resolve exclusively to public IP addresses.');
            }

            $validated[] = $address;
        }

        $validated = array_values(array_unique($validated));
        sort($validated, SORT_STRING);

        $normalizedHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $normalizedUrl = sprintf('%s://%s%s%s', $scheme, $normalizedHost, $path, $query);

        return new ValidatedHttpTarget(
            url: $normalizedUrl,
            scheme: $scheme,
            host: $host,
            port: $port,
            addresses: $validated,
            selectedIp: $validated[0],
            isIpLiteral: $isIpLiteral,
        );
    }

    private function isLocalHostname(string $host): bool
    {
        return in_array($host, ['localhost', 'localhost.localdomain'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal');
    }

    private static function isTestAllowedHost(string $host): bool
    {
        if (self::$testAllowedHosts === null) {
            return false;
        }

        foreach (self::$testAllowedHosts as $entry) {
            if ($entry === '') {
                continue;
            }

            if ($host === $entry || ($entry[0] === '.' && str_ends_with($host, $entry))) {
                return true;
            }
        }

        return false;
    }

    private function validateTestHost(string $scheme, string $host, int $port, string $path, string $query): ValidatedHttpTarget
    {
        try {
            $addresses = $this->dnsResolver->resolve($host);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Test-allowed host could not be resolved safely.', previous: $exception);
        }

        $ip = null;

        foreach ($addresses as $address) {
            if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false) {
                $ip = $address;

                break;
            }
        }

        if ($ip === null) {
            // Fall back to local resolution for environments (Herd, dnsmasq)
            // where the system resolver knows the host but the package
            // resolver does not.
            $local = gethostbyname($host);

            if ($local !== $host && filter_var($local, FILTER_VALIDATE_IP) !== false) {
                $ip = $local;
            }
        }

        if ($ip === null) {
            throw new InvalidArgumentException('Test-allowed host did not resolve to an IP address.');
        }

        return new ValidatedHttpTarget(
            url: sprintf('%s://%s%s%s', $scheme, $host, $path, $query),
            scheme: $scheme,
            host: $host,
            port: $port,
            addresses: [$ip],
            selectedIp: $ip,
            isIpLiteral: false,
        );
    }

    private function isPublicIp(string $ip): bool
    {
        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_GLOBAL_RANGE | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        $packed = inet_pton($ip);

        if (! is_string($packed)) {
            return false;
        }

        if (mb_strlen($packed) === 4) {
            return ord($packed[0]) < 224;
        }

        if (ord($packed[0]) === 0xFF) {
            return false;
        }

        $wellKnownNat64 = inet_pton('64:ff9b::');
        $localNat64 = inet_pton('64:ff9b:1::');

        return ! (
            is_string($wellKnownNat64)
            && mb_substr($packed, 0, 12) === mb_substr($wellKnownNat64, 0, 12)
        ) && ! (
            is_string($localNat64)
            && mb_substr($packed, 0, 6) === mb_substr($localNat64, 0, 6)
        );
    }
}
