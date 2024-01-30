<?php

namespace App\Database\Connector;

use Swoole\Coroutine\PostgreSQL;
use PDO;

class CoroutinePostgreSQLConnector implements ConnectorInterface
{

    public function connect(array|null $config = null): PostgreSQL|PDO
    {
        if (!isset($config['connection_strings'])) {
            throw new \InvalidArgumentException('The key "connection_string" is missing.');
        }
        $connection = new PostgreSQL();
        $ret = $connection->connect($config['connection_strings']);
        if ($ret === false) {
            throw new \RuntimeException(sprintf('Failed to connect PostgreSQL server: %s', $connection->error));
        }
        return $connection;
    }

    public function disconnect(PDO|PostgreSQL $connection): void
    {
        /**@var PostgreSQL $connection */
    }

    public function isConnected(PDO|PostgreSQL $connection): bool
    {
        /**@var PostgreSQL $connection */
        return true;
    }

    public function reset(PDO|PostgreSQL $connection):void
    {
        /**@var PostgreSQL $connection */
    }

    public function validate(PDO|PostgreSQL $connection): bool
    {
        return $connection instanceof PostgreSQL;
    }

}