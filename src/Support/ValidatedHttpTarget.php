<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

final readonly class ValidatedHttpTarget
{
    /**
     * @param  list<string>  $addresses
     */
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public array $addresses,
        public string $selectedIp,
        public bool $isIpLiteral,
    ) {}

    public function curlResolveEntry(): ?string
    {
        if ($this->isIpLiteral) {
            return null;
        }

        $resolvedAddress = str_contains($this->selectedIp, ':') ? '[' . $this->selectedIp . ']' : $this->selectedIp;

        return sprintf('%s:%d:%s', $this->host, $this->port, $resolvedAddress);
    }
}
