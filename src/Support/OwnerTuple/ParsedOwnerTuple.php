<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support\OwnerTuple;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class ParsedOwnerTuple
{
    public const string STATE_OWNER = 'owner';

    public const string STATE_EXPLICIT_GLOBAL = 'explicit_global';

    public const string STATE_UNRESOLVED = 'unresolved';

    private function __construct(
        public string $state,
        public ?string $owner_type,
        public string | int | null $owner_id,
    ) {}

    public static function owner(string $owner_type, string | int $owner_id): self
    {
        return new self(self::STATE_OWNER, $owner_type, $owner_id);
    }

    public static function explicitGlobal(): self
    {
        return new self(self::STATE_EXPLICIT_GLOBAL, null, null);
    }

    public static function unresolved(?string $owner_type = null, string | int | null $owner_id = null): self
    {
        return new self(self::STATE_UNRESOLVED, $owner_type, $owner_id);
    }

    public function isOwner(): bool
    {
        return $this->state === self::STATE_OWNER;
    }

    public function isExplicitGlobal(): bool
    {
        return $this->state === self::STATE_EXPLICIT_GLOBAL;
    }

    public function isUnresolved(): bool
    {
        return $this->state === self::STATE_UNRESOLVED;
    }

    public function toOwnerModel(): ?Model
    {
        if ($this->isExplicitGlobal()) {
            return null;
        }

        if (! $this->isOwner()) {
            throw new RuntimeException('Owner tuple is unresolved and cannot be converted to an owner model.');
        }

        return OwnerContext::fromTypeAndId($this->owner_type, $this->owner_id);
    }
}
