<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Data;

use InvalidArgumentException;

final readonly class WebhookRequestData
{
    /** @var array<string, list<string>> */
    public array $headers;

    /** @param array<string, list<string>> $headers */
    public function __construct(public string $body, array $headers = [])
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $key = mb_strtolower($name);

            if ($key === '' || ! array_is_list($values)) {
                throw new InvalidArgumentException('Invalid webhook header.');
            }

            foreach ($values as $value) {
                if (! is_string($value)) {
                    throw new InvalidArgumentException('Invalid webhook header.');
                }
            }

            $normalized[$key] = [...($normalized[$key] ?? []), ...$values];
        }

        $this->headers = $normalized;
    }

    public function header(string $name): ?string
    {
        $values = $this->headers[mb_strtolower($name)] ?? [];

        return count($values) === 1 ? $values[0] : null;
    }
}
