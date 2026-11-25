<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Data;

use InvalidArgumentException;

final readonly class RecordReference
{
    public function __construct(public string $type, public string $id)
    {
        if ($type === '' || $id === '') {
            throw new InvalidArgumentException('Record identity requires a type and identifier.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
