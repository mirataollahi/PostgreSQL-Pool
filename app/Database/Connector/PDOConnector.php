<?php

namespace App\Database\Connector;
use App\Core\Config;
use PDO;
use Swoole\Coroutine\PostgreSQL;

class PDOConnector implements ConnectorInterface
{

    public function connect(array|null $config = null): PDO|PostgreSQL
    {
        $config = [
            'host' => Config::get('DATABASE_HOST'),
            'port' => Config::get('DATABASE_PORT'),
            'dbname' => Config::get('DATABASE_NAME'),
            'charset' => Config::get('DATABASE_CHARSET'),
            'user' => Config::get('DATABASE_USERNAME'),
            'password' => Config::get('DATABASE_PASSWORD'),
        ];
        try {
            $connection = new PDO(
                "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};user={$config['user']};password={$config['password']}",
                $config['user'],
                $config['password']
            );

        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Failed to connect the requested database: [%d] %s', $e->getCode(), $e->getMessage()));
        }
        return $connection;
    }


    public function disconnect(PDO|PostgreSQL $connection): void
    {
        /**@var PDO $connection */
        $connection = null;
    }

    public function isConnected(PDO|PostgreSQL $connection): bool
    {
        /**@var PDO $connection */
        try {
            return !!@$connection->getAttribute(\PDO::ATTR_SERVER_INFO);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function reset(PDO|PostgreSQL $connection): void
    {

    }

    public function validate(PDO|PostgreSQL $connection): bool
    {
        return $connection instanceof \PDO;
    }

}