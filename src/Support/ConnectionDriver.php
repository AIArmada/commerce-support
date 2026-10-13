<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use LogicException;

final class ConnectionDriver
{
    public static function name(ConnectionInterface $connection): string
    {
        if ($connection instanceof Connection) {
            return $connection->getDriverName();
        }

        throw new LogicException(sprintf(
            'Expected a concrete database connection instance, got [%s].',
            $connection::class,
        ));
    }
}
