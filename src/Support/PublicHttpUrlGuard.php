<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Closure;
use InvalidArgumentException;
use Throwable;

/**
 * Validates outbound HTTP destinations before an application request is made.
 *
 * The guard fails closed for malformed URLs, credentials in URLs, non-HTTP
 * schemes, non-standard ports, localhost/private/reserved addresses, and DNS
 * names that do not resolve exclusively to public addresses.
 */
final class PublicHttpUrlGuard
{
    /** @var Closure(string): list<string> */
    private readonly Closure $dnsResolver;

    /**
     * @param  (callable(string): list<string>)|null  $dnsResolver
     */
    public function __construct(?callable $dnsResolver = null)
    {
        $this->dnsResolver = $dnsResolver !== null
            ? Closure::fromCallable($dnsResolver)
            : self::defaultDnsResolver(...);
    }

    public function isAllowed(string $url): bool
    {
        try {
            $this->assertAllowed($url);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertAllowed(string $url): void
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($url === '' || ! is_array($parts)) {
            throw new InvalidArgumentException('Outbound URL must be a valid absolute HTTP URL.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Outbound URL scheme must be HTTP or HTTPS.');
        }

        if (array_key_exists('user', $parts) || array_key_exists('pass', $parts)) {
            throw new InvalidArgumentException('Outbound URLs must not contain embedded credentials.');
        }

        $port = $parts['port'] ?? null;
        $expectedPort = $scheme === 'https' ? 443 : 80;

        if ($port !== null && (int) $port !== $expectedPort) {
            throw new InvalidArgumentException('Outbound URL port must match the standard port for its scheme.');
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), "[] \t\n\r\0\x0B."));

        if ($host === '') {
            throw new InvalidArgumentException('Outbound URL must include a host.');
        }

        if ($this->isLocalHostname($host)) {
            throw new InvalidArgumentException('Outbound URL host must not target a local hostname.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! $this->isPublicIp($host)) {
                throw new InvalidArgumentException('Outbound URL host must resolve to a public IP address.');
            }

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false || ! str_contains($host, '.')) {
            throw new InvalidArgumentException('Outbound URL host must be a valid fully-qualified domain name.');
        }

        try {
            $addresses = ($this->dnsResolver)($host);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'Outbound URL host could not be resolved safely.',
                previous: $exception,
            );
        }

        if ($addresses === []) {
            throw new InvalidArgumentException('Outbound URL host did not resolve to an IP address.');
        }

        foreach ($addresses as $address) {
            if (! is_string($address) || ! $this->isPublicIp($address)) {
                throw new InvalidArgumentException('Outbound URL host must resolve exclusively to public IP addresses.');
            }
        }
    }

    private function isLocalHostname(string $host): bool
    {
        return in_array($host, ['localhost', 'localhost.localdomain'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal');
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

        if (strlen($packed) === 4) {
            $firstOctet = ord($packed[0]);

            return $firstOctet < 224;
        }

        if (ord($packed[0]) === 0xff) {
            return false;
        }

        $wellKnownNat64 = inet_pton('64:ff9b::');
        $localNat64 = inet_pton('64:ff9b:1::');

        return ! (
            is_string($wellKnownNat64)
            && substr($packed, 0, 12) === substr($wellKnownNat64, 0, 12)
        ) && ! (
            is_string($localNat64)
            && substr($packed, 0, 6) === substr($localNat64, 0, 6)
        );
    }

    /**
     * @return list<string>
     */
    private static function defaultDnsResolver(string $hostname): array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $records = dns_get_record($hostname, DNS_A | DNS_AAAA);
        } finally {
            restore_error_handler();
        }

        if (! is_array($records)) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (is_string($record['ip'] ?? null)) {
                $addresses[] = $record['ip'];
            }

            if (is_string($record['ipv6'] ?? null)) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
