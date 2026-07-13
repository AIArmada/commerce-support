<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Contracts\PublicDnsResolver;

final class SystemPublicDnsResolver implements PublicDnsResolver
{
    public function resolve(string $hostname): array
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
