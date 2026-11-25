<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Hydrates trusted owner references at the framework boundary. The scoped
 * lifetime avoids repeating tenant lookup on every owned query.
 */
final class OwnerModelLookup
{
    /** @var array<string, Model> */
    private array $owners = [];

    public function resolve(RecordReference $reference): Model
    {
        $key = mb_strlen($reference->type, '8bit') . ':' . $reference->type . ':' . $reference->id;

        if (isset($this->owners[$key])) {
            return $this->owners[$key];
        }

        $model = OwnerContext::fromTypeAndId($reference->type, $reference->id);

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel($reference->type, [$reference->id]);
        }

        // The resolver has selected the trusted tenant. Do not recursively
        // apply the owner scope while loading the owner itself.
        return $this->owners[$key] = $model->newQueryWithoutScope(OwnerScope::class)
            ->whereKey($reference->id)
            ->firstOrFail();
    }

    public function clear(): void
    {
        $this->owners = [];
    }
}
