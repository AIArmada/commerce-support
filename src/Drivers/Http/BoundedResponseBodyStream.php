<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Drivers\Http;

use GuzzleHttp\Psr7\Stream;
use RuntimeException;

final class BoundedResponseBodyStream extends Stream
{
    private int $bytesWritten = 0;

    public function __construct(private readonly int $maxBytes)
    {
        $resource = fopen('php://temp', 'w+');

        if ($resource === false) {
            throw new RuntimeException('Unable to open a temporary response body stream.');
        }

        parent::__construct($resource);
    }

    /**
     * @param  string  $string
     */
    public function write(mixed $string): int
    {
        $string = (string) $string;
        $length = mb_strlen($string, '8bit');

        if ($this->bytesWritten + $length > $this->maxBytes) {
            throw new ResponseBodyLimitExceeded('The response body exceeded the configured byte limit.');
        }

        $written = parent::write($string);
        $this->bytesWritten += $written;

        return $written;
    }
}
