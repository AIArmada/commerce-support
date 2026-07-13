<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts;

interface PublicDnsResolver
{
    /** @return list<string> */
    public function resolve(string $hostname): array;
}
