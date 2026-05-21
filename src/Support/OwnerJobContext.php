<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class OwnerJobContext
{
    public function __construct(
        public ?string $ownerType,
        public string | int | null $ownerId,
        public bool $ownerIsGlobal = false,
    ) {
        if ($this->ownerType === '') {
            throw new InvalidArgumentException('Owner type must not be an empty string.');
        }

        if (is_string($this->ownerId) && $this->ownerId === '') {
            throw new InvalidArgumentException('Owner id must not be an empty string.');
        }

        if ($this->ownerIsGlobal && ($this->ownerType !== null || $this->ownerId !== null)) {
            throw new InvalidArgumentException('ownerIsGlobal=true cannot be combined with ownerType/ownerId values.');
        }

        if (($this->ownerType === null) xor ($this->ownerId === null)) {
            throw new InvalidArgumentException('ownerType and ownerId must both be set or both be null.');
        }
    }

    public static function explicitGlobal(): self
    {
        return new self(ownerType: null, ownerId: null, ownerIsGlobal: true);
    }

    public static function fromOwnerModel(Model $owner): self
    {
        return new self(
            ownerType: $owner::class,
            ownerId: $owner->getKey(),
            ownerIsGlobal: false,
        );
    }

    public function isExplicitGlobal(): bool
    {
        return $this->ownerIsGlobal;
    }

    public function toOwnerModel(): ?Model
    {
        if ($this->ownerType === null || $this->ownerId === null) {
            return null;
        }

        return OwnerContext::fromTypeAndId($this->ownerType, $this->ownerId);
    }
}
