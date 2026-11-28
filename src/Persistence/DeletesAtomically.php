<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Persistence;

use Throwable;

trait DeletesAtomically
{
    public function delete(): ?bool
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            $deleted = parent::delete();

            if ($deleted === false) {
                $connection->rollBack();

                return false;
            }

            $connection->commit();

            return $deleted;
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
